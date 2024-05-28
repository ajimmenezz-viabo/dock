<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Accounts\AccountController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet\AccountWallet;
use Illuminate\Http\Request;
use App\Models\Wallet\WalletMovement;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\DB;

use Exception;

class DepositController extends Controller
{
    public function to_account(Request $request)
    {
        try {
            DB::beginTransaction();

            $this->validate_to_account_request($request);
            if (!$this->validate_account($request->account_id)) {
                return self::error('The account does not exist or you do not have permission to access it', 404, new Exception('Account not found'));
            }

            $wallet = $this->get_account_wallet($request->account_id);

            $movement = new WalletMovement();
            $movement->UUID = Uuid::uuid7()->toString();
            $movement->WalletId = $wallet->Id;
            $movement->ApprovedBy = auth()->user()->Id;
            $movement->Type = 'Deposit';
            $movement->Description = $request->description;
            $movement->Amount = $request->amount;
            $movement->Balance = floatval($wallet->Balance) + floatval($request->amount);
            $movement->Reference = $request->reference ?? null;
            $movement->save();

            $wallet->Balance = $this->encrypter->encrypt(floatval($wallet->Balance) + floatval($request->amount));
            $wallet->save();


            DB::commit();
            return response()->json(WalletController::movement_object($movement), 200);
        } catch (Exception $e) {
            DB::rollBack();
            return self::error('Invalid request, please check the documentation', 400, $e);
        }
    }

    private function validate_to_account_request($request)
    {
        $this->validate($request, [
            'account_id' => 'required',
            'amount' => 'required|numeric',
            'description' => 'required|string'
        ]);
    }

    private function validate_account($id)
    {
        return User::where('Id', $id)->where('profile', 'admin_account')->first();
    }

    private function get_account_wallet($account_id)
    {
        $accountWallet = AccountWallet::where('AccountId', $account_id)
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
                'AccountId' => $account_id,
                'Balance' => $this->encrypter->encrypt('0.00')
            ]);
        }

        return $accountWallet;
    }
}
