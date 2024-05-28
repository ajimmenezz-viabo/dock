<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Wallet\WalletController;
use App\Models\Account\Subaccount;
use App\Models\Shared\AvailableSTPAccount;
use App\Models\Wallet\AccountWallet;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\DB;

use Exception;

/**
 * @OA\Info(title="API Documentation", version="1.0")
 */

class AccountController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/v1/account",
     *      tags={"Account"},
     *      summary="Get account associated with the authenticated user",
     *      description="Get account associated with the authenticated user",
     *      security={{"bearerAuth":{}}},
     * 
     *      @OA\Response(
     *         response=200,
     *          description="Account retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="account", type="string", example="Account Name", description="Account Name"),
     *              @OA\Property(property="wallet", type="object",
     *                  @OA\Property(property="wallet_id", type="string", example="123456", description="Wallet UUID"),
     *                  @OA\Property(property="balance", type="string", example="100.00", description="Wallet Balance"),
     *                  @OA\Property(property="clabe", type="string", example="123456", description="Wallet CLABE"),
     *                  @OA\Property(property="last_movements", type="array",
     *                      @OA\Items(   
     *                          @OA\Property(property="movement_id", type="string", example="123456", description="Movement UUID"),
     *                          @OA\Property(property="card", type="object",
     *                              @OA\Property(property="card_id", type="string", example="123456", description="Card UUID"),
     *                              @OA\Property(property="masked_pan", type="string", example="123456", description="Card Masked PAN"),
     *                           ),
     *                          @OA\Property(property="type", type="string", example="deposit", description="Movement Type"),
     *                          @OA\Property(property="description", type="string", example="Deposit", description="Movement Description"),
     *                          @OA\Property(property="amount", type="string", example="100.00", description="Movement Amount"),
     *                          @OA\Property(property="balance", type="string", example="100.00", description="Movement Balance"),
     *                          @OA\Property(property="date", type="string", example="1716611739", description="Movement Date / Unix Timestamp"),
     *                     )
     *                  )
     *              )
     *          )    
     *      ),
     * 
     * 
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthorized | Error while decoding the token", description="Message")
     *         )
     *    ),
     * 
     *    @OA\Response(
     *      response=403,
     *      description="Forbidden",
     *      @OA\JsonContent(
     *          @OA\Property(property="message", type="string", example="You don't have permission to access this resource", description="Message")
     *     )
     *   ),
     * )
     */
    public function index()
    {
        try {
            if (auth()->user()->profile == 'admin_account') {
                return response()->json($this->accountObject(auth()->user()->Id), 200);
            } else {
                return response()->json(['message' => 'You don\'t have permission to access this resource'], 403);
            }
        } catch (Exception $e) {
            return self::error('Error getting subaccounts', 400, $e);
        }
    }

    private function fixNonSTPAccountWallet($accountWallet)
    {
        $availableSTPAccount = AvailableSTPAccount::where('Available', true)->first();

        if ($availableSTPAccount && $accountWallet->STPAccount == null) {
            AccountWallet::where('Id', $accountWallet->Id)->update(['STPAccount' => $availableSTPAccount->STPAccount]);
            AvailableSTPAccount::where('STPAccount', $availableSTPAccount->STPAccount)->update(['Available' => false]);
        }

        return AccountWallet::where('Id', $accountWallet->Id)->first();
    }

    private function fixNonAccountWallet()
    {
        $accountWallet = AccountWallet::where('AccountId', auth()->user()->Id)
            ->where('SubAccountId', null)
            ->first();

        if (!$accountWallet) {
            while (true) {
                $uuid = Uuid::uuid7()->toString();
                $exists = AccountWallet::where('UUID', $uuid)->first();
                if (!$exists) break;
            }

            $accountWallet = AccountWallet::create([
                'UUID' => $uuid,
                'AccountId' => auth()->user()->Id,
                'Balance' => $this->encrypter->encrypt('0.00')
            ]);
        }

        $this->fixNonSTPAccountWallet($accountWallet);
    }

    public function accountObject($account_id)
    {
        $this->fixNonAccountWallet();

        $wallet = AccountWallet::where('AccountId', $account_id)
            ->where('SubAccountId', null)
            ->first();

        $object = [
            'account' => auth()->user()->name,
            'wallet' => [
                'wallet_id' => $wallet->UUID,
                'balance' => $this->encrypter->decrypt($wallet->Balance),
                'clabe' => $wallet->STPAccount,
                'last_movements' => WalletController::last_movements($wallet->Id)
            ]
        ];


        return $object;
    }


    /**
     * @OA\Get(
     *      path="/api/v1/account/movements",
     *      tags={"Account"},
     *      summary="Get all movements for a specific account",
     *      description="Get all movements for a specific account",
     *      
     *      security={{"bearerAuth":{}}},
     * 
     *      @OA\RequestBody(
     *          required=false,
     *          @OA\JsonContent(
     *              @OA\Property(property="from", type="string", example="2024-05-23 04:52:41", description="From date"),
     *              @OA\Property(property="to", type="string", example="2024-05-23 04:52:41", description="To date")
     *          )
     *      ),
     * 
     *      @OA\Response(
     *          response="200",
     *          description="Movements retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="movements", type="array", 
     *                   @OA\Items(
     *                       @OA\Property(property="movement_id", type="string", example="123456", description="Movement UUID"),    
     *                       @OA\Property(property="card", type="object",
     *                           @OA\Property(property="card_id", type="string", example="123456", description="Card UUID"),     
     *                           @OA\Property(property="masked_pan", type="string", example="123456", description="Card Masked PAN"),
     *                       ),
     *                       @OA\Property(property="type", type="string", example="deposit", description="Movement Type"),
     *                       @OA\Property(property="description", type="string", example="Deposit", description="Movement Description"),
     *                       @OA\Property(property="amount", type="string", example="100.00", description="Movement Amount"),
     *                       @OA\Property(property="balance", type="string", example="100.00", description="Movement Balance"),
     *                       @OA\Property(property="date", type="string", example="1716611739", description="Movement Date / Unix Timestamp"),
     *                    )
     *               )
     *          )
     *      ),
     *
     *  )
     */
    public function movements(Request $request)
    {
        try {
            if (auth()->user()->profile == 'admin_account') {
                $from = $request['from'] ?? date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' - 1 month'));
                $to = $request['to'] ?? date('Y-m-d H:i:s');

                if ($from > $to) return response()->json(['message' => 'Invalid date range'], 400);

                if (strtotime($to) - strtotime($from) > (2592000 * 3)) return response()->json(['message' => 'Date range exceeds 90 days'], 400);

                $this->fixNonAccountWallet();

                $wallet = AccountWallet::where('AccountId', auth()->user()->Id)
                    ->where('SubAccountId', null)
                    ->first();

                $movements = WalletController::movements($wallet->Id, $from, $to);

                return response()->json([
                    'movements' => $movements['movements'],
                    'total_records' => $movements['count'],
                    'from' => $from,
                    'to' => $to
                ], 200);
            } else {
                return response()->json(['message' => 'You don\'t have permission to access this resource'], 403);
            }
        } catch (Exception $e) {
            return self::error('Error getting account movements', 400, $e);
        }
    }
}
