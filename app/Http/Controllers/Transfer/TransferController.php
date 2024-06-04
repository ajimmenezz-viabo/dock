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

        if($request->sourceType == 'card' && $request->destinationType == 'card' && $request->source == $request->destination){
            return self::error('Transfer between the same card is not allowed', 400, new Exception('Transfer between the same card is not allowed'));
        }

        if($request->sourceType == 'subaccount' && $request->destinationType == 'subaccount' && $request->source == $request->destination){
            return self::error('Transfer between the same subaccount is not allowed', 400, new Exception('Transfer between the same subaccount is not allowed'));
        }

        $balance = $this->validateBalance($request->sourceType, $request->source, $request->amount);
        if ($balance != '') {
            return self::error($balance, 400, new Exception($balance));
        }

        try {
            DB::beginTransaction();
            $origin = $this->publishOriginTransaction($request->sourceType, $request->source, $request->amount, $request->description, $request->destinationType);
            $destination = $this->publishDestinationTransaction($request->destinationType, $request->destination, $request->amount, $request->description, $request->sourceType);
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

    private function publishOriginTransaction($type, $uuid, $amount, $description, $destination)
    {
        $description = "Transfer to {$destination}. " . $description;
        $amount = abs($amount);

        switch ($type) {
            case 'account':
                return $this->registerAccountTransaction(($amount * -1), $description);
                break;
            case 'subaccount':
                return $this->registerSubaccountTransaction($uuid, ($amount * -1), $description);
                break;
            case 'card':
                return $this->registerCardTransaction($uuid, ($amount * -1), $description);
                break;
            default:
                abort(400, 'Invalid origin type');
                break;
        }
    }

    private function publishDestinationTransaction($type, $uuid, $amount, $description, $destination)
    {
        $description = "Transfer from {$destination}. " . $description;
        $amount = abs($amount);

        switch ($type) {
            case 'account':
                return $this->registerAccountTransaction($amount, $description);
                break;
            case 'subaccount':
                return $this->registerSubaccountTransaction($uuid, $amount, $description);
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

    private function registerSubaccountTransaction($uuid, $amount, $description, $reference = null)
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
