<?php

namespace App\Http\Controllers\Transfer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Wallet\WalletController;
use App\Models\Account\Subaccount;
use App\Models\Card\Card;
use App\Models\CardMovements\CardMovements;
use App\Models\Shared\AuthorizationRequest;
use App\Models\Wallet\AccountWallet;
use App\Models\Wallet\WalletMovement;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class TransferController extends Controller
{
    /**
     * @OA\Post(
     *      path="/api/v1/transfer",
     *      tags={"Transfer"},
     *      summary="Transfer funds between account, subaccount or card",
     *      description="Transfer funds between account, subaccount or card",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="sourceType", type="string", example="account", description="Source type (account, subaccount, card)"),
     *              @OA\Property(property="source", type="string", example="123456", description="Source UUID"),
     *              @OA\Property(property="destinationType", type="string", example="subaccount", description="Destination type (account, subaccount, card)"),
     *              @OA\Property(property="destination", type="string", example="123456", description="Destination UUID"),
     *              @OA\Property(property="amount", type="number", example="100.00", description="Amount to transfer"),
     *              @OA\Property(property="description", type="string", example="Transfer funds", description="Transfer description")
     *          )
     *      ),
     * 
     *     @OA\Response(
     *          response="200",
     *          description="Transfer funds successfully (Origin Account or Subaccount)",
     *          @OA\JsonContent(
     *              @OA\Property(property="new_balance", type="string", example="100.00", description="New balance"),
     *              @OA\Property(property="movement", type="object",
     *                  @OA\Property(property="movement_id", type="string", example="123456", description="Movement UUID"),
     *                  @OA\Property(property="date", type="string", example="1716611739", description="Movement Date / Unix Timestamp"),
     *                  @OA\Property(property="type", type="string", example="deposit", description="Movement Type"),
     *                  @OA\Property(property="amount", type="string", example="100.00", description="Movement Amount"),
     *                  @OA\Property(property="authorization_code", type="string", example="123456", description="Authorization Code"),
     *                  @OA\Property(property="description", type="string", example="Deposit", description="Movement Description"),
     *              )
     *          )
     *      ),
     * 
     * 
     *      @OA\Response(
     *          response="400",
     *          description="Error transferring funds",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Error transferring funds", description="Error message")
     *          )
     *      ),
     * 
     *      @OA\Response( 
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized | Error while decoding the token", description="Message")
     *          )
     *     )
     *
     * )
     */

    public function transfer(Request $request)
    {
        $this->validateTransferData($request);
        if (!$this->validateAccountOnly($request)) {
            return self::error('Transfer between accounts is not allowed', 400, new Exception('Transfer between accounts is not allowed'));
        }

        $originOwner = $this->validateOwner($request->sourceType, $request->source, "origin");
        if ($originOwner != '') {
            return self::error($originOwner, 400, new Exception($originOwner));
        }

        $destinationOwner = $this->validateOwner($request->destinationType, $request->destination, "destination");
        if ($destinationOwner != '') {
            return self::error($destinationOwner, 400, new Exception($destinationOwner));
        }

        if ($request->sourceType == 'card' && $request->destinationType == 'card' && $request->source == $request->destination) {
            return self::error('Transfer between the same card is not allowed', 400, new Exception('Transfer between the same card is not allowed'));
        }

        if ($request->sourceType == 'subaccount' && $request->destinationType == 'subaccount') {
            return self::error('Transfer between the same subaccount is not allowed', 400, new Exception('Transfer between subaccounts is not allowed'));
        }

        if ($request->sourceType == 'account' && $request->destinationType == 'card') {
            return self::error('Transfer from account to card is not allowed. You must transfer to a subaccount first', 400, new Exception('Transfer from account to card is not allowed. You must transfer to a subaccount first'));
        }

        $balance = $this->validateBalance($request->sourceType, $request->source, $request->amount);
        if ($balance != '') {
            return self::error($balance, 400, new Exception($balance));
        }

        try {
            DB::beginTransaction();
            if ($request->sourceType == 'card' && $request->destinationType == 'card') {
                $originCard = Card::where('UUID', $request->source)->first();
                $destinationCard = Card::where('UUID', $request->destination)->first();
                $originCardSubaccount = Subaccount::where('Id', $originCard->SubAccountId)->first();
                $origin = $this->publishOriginTransaction($request->sourceType, $request->source, $request->amount, $request->description, "subaccount");
                sleep(1);
                $this->publishDestinationTransaction("subaccount", $originCardSubaccount->UUID, $request->amount, $request->description, $request->sourceType, $originCard->Id);
                sleep(1);
                $this->publishOriginTransaction("subaccount", $originCardSubaccount->UUID, $request->amount, $request->description, $request->destinationType, $destinationCard->Id);
                sleep(1);
                $this->publishDestinationTransaction($request->destinationType, $request->destination, $request->amount, $request->description, "subaccount");
            } else {
                $origin = $this->publishOriginTransaction($request->sourceType, $request->source, $request->amount, $request->description, $request->destinationType);
                sleep(1);
                $destination = $this->publishDestinationTransaction($request->destinationType, $request->destination, $request->amount, $request->description, $request->sourceType);
            }
            DB::commit();

            return response()->json([
                'new_balance' => $origin['balance'],
                'movement' => $origin['movement']
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return self::error('Error transferring funds', 500, $e);
        }
    }

    private function validateTransferData(Request $request)
    {
        $this->validate($request, [
            'sourceType' => 'required',
            'destinationType' => 'required',
            'amount' => 'required|numeric',
            'description' => 'required'
        ]);
    }

    private function validateAccountOnly(Request $request)
    {
        if ($request->sourceType == 'account' && $request->destinationType == 'account') {
            return false;
        }
        return true;
    }

    private function validateOwner($type, $uuid, $originType = "origin")
    {

        switch ($type) {
            case 'account':
                return '';
                break;
            case 'subaccount':
                $subaccount = Subaccount::where('UUID', $uuid)->where('AccountId', auth()->user()->Id)->first();
                if ($subaccount) {
                    return '';
                }
                return "The {$originType} subaccount not found or you do not have permission to access it";
                break;
            case 'card':
                $card = Card::where('UUID', $uuid)->where('CreatorId', auth()->user()->Id)->first();
                if ($card) {
                    return '';
                }
                return "The {$originType} card not found or you do not have permission to access it";
                break;
            default:
                return "Invalid {$originType} type";
                break;
        }
    }

    private function validateBalance($type, $uuid, $amount)
    {
        $balance = 0;
        switch ($type) {
            case 'account':
                $wallet = AccountWallet::where('AccountId', auth()->user()->Id)->where('SubAccountId', null)->first();
                if ($wallet) {
                    $balance = self::decrypt($wallet->Balance);
                }
                break;
            case 'subaccount':
                $subaccount = Subaccount::where('UUID', $uuid)->where('AccountId', auth()->user()->Id)->first();
                $wallet = AccountWallet::where('AccountId', auth()->user()->Id)->where('SubAccountId', $subaccount->Id)->first();
                if ($wallet) {
                    $balance = self::decrypt($wallet->Balance);
                }
                break;
            case 'card':
                $card = Card::where('UUID', $uuid)->where('CreatorId', auth()->user()->Id)->first();
                if ($card) {
                    $balance = self::decrypt($card->Balance);
                }
                break;
            default:
                return "Invalid origin type";
                break;
        }

        if (floatval($balance) < floatval($amount)) {
            return "Insufficient funds";
        } else {
            return '';
        }
    }

    private function publishOriginTransaction($type, $uuid, $amount, $description, $destination, $cardId = null)
    {
        $description = "Transfer to {$destination}. " . $description;
        $amount = abs($amount);

        switch ($type) {
            case 'account':
                return $this->registerAccountTransaction(($amount * -1), $description);
                break;
            case 'subaccount':
                return $this->registerSubaccountTransaction($uuid, ($amount * -1), $description, null, $cardId);
                break;
            case 'card':
                return $this->registerCardTransaction($uuid, ($amount * -1), $description);
                break;
            default:
                abort(400, 'Invalid origin type');
                break;
        }
    }

    private function publishDestinationTransaction($type, $uuid, $amount, $description, $destination, $cardId = null)
    {
        $description = "Transfer from {$destination}. " . $description;
        $amount = abs($amount);

        switch ($type) {
            case 'account':
                return $this->registerAccountTransaction($amount, $description);
                break;
            case 'subaccount':
                return $this->registerSubaccountTransaction($uuid, $amount, $description, null, $cardId);
                break;
            case 'card':
                return $this->registerCardTransaction($uuid, $amount, $description);
                break;
            default:
                abort(400, 'Invalid origin type');
                break;
        }
    }

    private function registerAccountTransaction($amount, $description, $reference = null)
    {
        $wallet = AccountWallet::where('AccountId', auth()->user()->Id)->where('SubAccountId', null)->first();
        if (!$wallet) {
            $wallet = AccountWallet::create([
                'UUID' => Uuid::uuid7()->toString(),
                'AccountId' => auth()->user()->Id,
                'SubAccountId' => null,
                'Balance' => self::encrypt('0.00')
            ]);
        }

        $balance = self::decrypt($wallet->Balance);

        $newBalance = floatval($balance) + floatval($amount);

        $wallet->Balance = self::encrypt($newBalance);
        $wallet->save();

        $movement = WalletMovement::create([
            'UUID' => Uuid::uuid7()->toString(),
            'WalletId' => $wallet->Id,
            'ApprovedBy' => auth()->user()->Id,
            'Type' => ($amount < 0 ? 'Transfer Out' : 'Transfer In'),
            'Description' => $description,
            'Amount' => floatval($amount),
            'Balance' => floatval($newBalance),
            'Reference' => $reference
        ]);

        return [
            'movement' => WalletController::movement_object($movement),
            'balance' => $newBalance
        ];
    }

    private function registerSubaccountTransaction($uuid, $amount, $description, $reference = null, $cardId = null)
    {
        $subaccount = Subaccount::where('UUID', $uuid)->where('AccountId', auth()->user()->Id)->first();

        $wallet = AccountWallet::where('AccountId', auth()->user()->Id)->where('SubAccountId', $subaccount->Id)->first();
        if (!$wallet) {
            $wallet = AccountWallet::create([
                'UUID' => Uuid::uuid7()->toString(),
                'AccountId' => auth()->user()->Id,
                'SubAccountId' => $subaccount->Id,
                'Balance' => self::encrypt('0.00')
            ]);
        }

        $balance = self::decrypt($wallet->Balance);

        $newBalance = floatval($balance) + floatval($amount);

        $wallet->Balance = self::encrypt($newBalance);
        $wallet->save();

        $movement = WalletMovement::create([
            'UUID' => Uuid::uuid7()->toString(),
            'WalletId' => $wallet->Id,
            'ApprovedBy' => auth()->user()->Id,
            'CardId' => $cardId,
            'Type' => ($amount < 0 ? 'Transfer Out' : 'Transfer In'),
            'Description' => $description,
            'Amount' => floatval($amount),
            'Balance' => floatval($newBalance),
            'Reference' => $reference
        ]);

        return [
            'movement' => WalletController::movement_object($movement),
            'balance' => $newBalance
        ];
    }

    private function registerCardTransaction($uuid, $amount, $description)
    {
        $card = Card::where('UUID', $uuid)->first();
        $cardBalance = self::decrypt($card->Balance);

        $newBalance = floatval($cardBalance) + floatval($amount);

        $card->Balance = self::encrypt($newBalance);
        $card->save();

        $authorization = AuthorizationRequest::create([
            'UUID' => Uuid::uuid7()->toString(),
            'ExternalId' => '',
            'AuthorizationCode' => self::getAuthorizationCode('TR'),
            'Endpoint' => 'transfer',
            'Headers' => '',
            'Body' => '',
            'Response' => '',
            'Error' => '',
            'Code' => 200,
            'CardExternalId' => $card->ExternalId
        ]);

        $movement = CardMovements::create([
            'UUID' => Uuid::uuid7()->toString(),
            'CardId' => $card->Id,
            'AuthorizationRequestId' => $authorization->Id,
            'Type' => 'TRANSFER',
            'Description' => $description,
            'Amount' => floatval($amount),
            'Balance' => floatval($newBalance)
        ]);

        return [
            'movement' => WalletController::movement_object($movement),
            'balance' => $newBalance
        ];
    }


    public static function getAuthorizationCode($prefix)
    {
        do {
            $number = '';
            for ($i = 0; $i < 6; $i++) {
                $number .= rand(0, 9);
            }

            $code = $prefix . $number;
        } while (AuthorizationRequest::where('AuthorizationCode', $code)->exists());

        return $code;
    }
}
