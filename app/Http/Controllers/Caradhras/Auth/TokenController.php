<?php

namespace App\Http\Controllers\Caradhras\Auth;

use App\Models\Security\DockJwt;
use App\Services\DockApiService;
use Laravel\Lumen\Routing\Controller as BaseController;
use Carbon\Carbon;
use Exception;


class TokenController extends BaseController
{

    static public function get()
    {
        try {
            $token = self::localToken();
            if (is_null($token)) {
                $token = self::getDockToken();
            }

            return $token;
        } catch (Exception $e) {
            return '';
        }
    }

    static private function localToken()
    {
        $token = DockJwt::first();
        if ($token && $token->updated_at->diffInMinutes(Carbon::now()) < 58) {
            return $token->Token;
        }

        return null;
    }

    static private function getDockToken()
    {
        $response = DockApiService::request(
            ((env('APP_ENV') === 'production') ? env('PRODUCTION_AUTH_URL') : env('STAGING_AUTH_URL')) . 'oauth2/token?grant_type=client_credentials',
            'POST',
            [],
            [],
            'basic'
        );

        $response_decoded = json_decode($response);

        if (isset($response_decoded->access_token)) {
            self::updateToken($response_decoded->access_token);
            return $response_decoded->access_token;
        } else {
            return '';
        }
    }

    static private function updateToken($access_token)
    {
        $token = DockJwt::first();
        if ($token) {
            $token->Token = $access_token;
            $token->save();
        } else {
            $token =
                DockJwt::create([
                    'Token' => $access_token
                ]);
        }
    }
}
