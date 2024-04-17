<?php

namespace App\Http\Controllers\Security;

use Illuminate\Http\Request;

class Decrypt
{
    public function decrypt(Request $request)
    {
        $iv = base64_decode($request->input('iv'));
        $decrypted = \openssl_decrypt(
            $request->input('value'),
            'aes-256-cbc',
            env('APP_KEY'),
            0,
            $iv
        );

        return response()->json(['decrypted' => unserialize($decrypted)], 200);
    }
}
