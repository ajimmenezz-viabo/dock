<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\User;

use Exception;

class AccountAdminController extends Controller
{
    public function index($id)
    {
        try {
            if (!User::where('Id', $id)->first()) {
                return self::error('The account does not exist or you do not have permission to access it', 404, new Exception('Account not found'));
            }

            return response()->json(AccountController::accountObject($id), 200);
        } catch (Exception $e) {
            return self::error('Error getting subaccounts', 400, $e);
        }
    }
}
