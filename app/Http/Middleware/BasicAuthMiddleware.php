<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class BasicAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $authorization = $request->header('Authorization');

        if (!$authorization || !$this->isValidCredentials($authorization)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }

    private function isValidCredentials($authorization)
    {
        $credentials = base64_decode(str_replace('Basic ', '', $authorization));
        list($username, $password) = explode(':', $credentials);

        return $username === env('BASIC_AUTH_USER') && $password === env('BASIC_AUTH_PASS');
    }
}
