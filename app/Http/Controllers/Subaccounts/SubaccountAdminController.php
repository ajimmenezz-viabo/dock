<?php

namespace App\Http\Controllers\Subaccounts;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Subaccounts\SubaccountController;
use App\Models\Account\Subaccount;
use App\Models\User;

use Exception;

class SubaccountAdminController extends Controller
{
    public function index($account_id)
    {
        try {
            if (!User::where('Id', $account_id)->first()) {
                return self::error('The account does not exist or you do not have permission to access it', 404, new Exception('Account not found'));
            }

            $subaccounts = Subaccount::where('AccountId', $account_id)->get();
            $subaccountsArray = [];
            foreach ($subaccounts as $subaccount) {
                $subaccountsArray[] = SubaccountController::subaccountObject($subaccount->Id);
            }

            return response()->json($subaccountsArray, 200);
        } catch (Exception $e) {
            return self::error('Error getting subaccounts', 400, $e);
        }
    }
}
