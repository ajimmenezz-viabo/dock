<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use Exception;

class Dev extends Controller
{
    public function create_user(Request $request)
    {
        $this->validateUserData($request);

        try {
            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->profile = $request->profile;
            $user->save();

            return response()->json([
                'message' => 'User created successfully'
            ], 201);
        } catch (Exception $e) {
            return self::error('Error creating user', 400, $e);
        }
    }

    public function validateUserData($request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string',
            'profile' => 'required|string'
        ]);
    }
}
