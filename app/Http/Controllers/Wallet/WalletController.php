<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Exception;

class WalletController extends Controller
{
    public function register_deposit_by_uuid(Request $request, $uuid)
    {
        try {
            
        } catch (Exception $e) {
            return self::error('Error registering deposit', 400, $e);
        }
    }
}
