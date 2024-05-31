<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Accounts\AccountController;
use App\Models\User;
use App\Models\Account\Subaccount;
use Illuminate\Http\Request;
use App\Models\Wallet\WalletMovement;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Subaccounts\SubaccountController;

use Exception;

class ReversalController extends Controller
{
    public function from_account(Request $request)
    {
        try {
            DB::beginTransaction();

            $this->validate_from_account_request($request);
            if (!$this->validate_account($request->account_id)) {
                return self::error('The account does not exist or you do not have permission to access it', 404, new Exception('Account not found'));
            }

            if ($request->amount <= 0) {
                return self::error('The amount must be greater than 0, even for reversals', 400, new Exception('Invalid amount'));
            }

            if (WalletMovement::where('Type', 'Reversal')
                ->where('Reference', $request->reference)
                ->first()
            ) {
                return self::error('There is already a reversal with the same reference', 400, new Exception('Duplicate reference'));
            }

            $wallet = AccountController::fixNonAccountWallet($request->account_id);

            $wallet_balance = floatval(self::decrypt($wallet->Balance));

            if ($wallet_balance < floatval($request->amount)) {
                return self::error('The account does not have enough balance to perform the reversal', 400, new Exception('Insufficient balance.'));
            }

            $movement = new WalletMovement();
            $movement->UUID = Uuid::uuid7()->toString();
            $movement->WalletId = $wallet->Id;
            $movement->ApprovedBy = auth()->user()->Id;
            $movement->Type = 'Reversal';
            $movement->Description = $request->description;
            $movement->Amount = floatval($request->amount) * -1;
            $movement->Balance = floatval($wallet_balance) - floatval($request->amount);
            $movement->Reference = $request->reference ?? null;
            $movement->save();

            $wallet->Balance = self::encrypt(floatval($wallet_balance) - floatval($request->amount));
            $wallet->save();


            DB::commit();
            return response()->json(WalletController::movement_object($movement), 200);
        } catch (Exception $e) {
            DB::rollBack();
            return self::error('Invalid request, please check the documentation', 400, $e);
        }
    }

    private function validate_from_account_request($request)
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

    public function from_subaccount(Request $request)
    {
        try {
            DB::beginTransaction();

            $this->validate_from_subaccount_request($request);

            $subaccount = $this->validate_subaccount($request->subaccount_id);

            if (!$subaccount) {
                return self::error('The sub-account does not exist or you do not have permission to access it', 404, new Exception('Sub-account not found'));
            }

            if (WalletMovement::where('Type', 'Reversal')
                ->where('Reference', $request->reference)
                ->first()
            ) {
                return self::error('There is already a reversal with the same reference', 400, new Exception('Duplicate reference'));
            }

            $wallet = SubaccountController::fixNonSubaccountWallet($subaccount->AccountId, $subaccount->Id);
            $wallet_balance = floatval(self::decrypt($wallet->Balance));

            $movement = new WalletMovement();
            $movement->UUID = Uuid::uuid7()->toString();
            $movement->WalletId = $wallet->Id;
            $movement->ApprovedBy = auth()->user()->Id;
            $movement->Type = 'Reversal';
            $movement->Description = $request->description;
            $movement->Amount = floatval($request->amount) * -1;
            $movement->Balance = floatval($wallet_balance) - floatval($request->amount);
            $movement->Reference = $request->reference ?? null;
            $movement->save();

            $wallet->Balance = self::encrypt(floatval($wallet_balance) - floatval($request->amount));
            $wallet->save();

            DB::commit();
            return response()->json(WalletController::movement_object($movement), 200);
        } catch (Exception $e) {
            DB::rollBack();
            return self::error('Invalid request, please check the documentation', 400, $e);
        }
    }

    private function validate_from_subaccount_request($request)
    {
        $this->validate($request, [
            'subaccount_id' => 'required',
            'amount' => 'required|numeric',
            'description' => 'required|string'
        ]);
    }

    private function validate_subaccount($subaccount_id)
    {
        return Subaccount::where('UUID', $subaccount_id)->first();
    }
}
