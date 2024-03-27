<?php

namespace App\Http\Controllers\Caradhras\Auth;

use App\Models\Security\DockJwt;
use App\Services\DockApiService;
use Laravel\Lumen\Routing\Controller as BaseController;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;

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
        try {
            $client_options = [];
            if(env('APP_ENV') !== 'production') {
                $client_options = [
                    RequestOptions::PROXY => [
                        'http'  => env('PROXY'),
                        'https' => env('PROXY')
                    ],
                    RequestOptions::VERIFY => false,
                    RequestOptions::TIMEOUT => 30
                ];
            }

            $client = new \GuzzleHttp\Client($client_options);

            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode(env('DOCK_API_CLIENT_U') . ':' . env('DOCK_API_CLIENT_P'))
            ];

            $response = $client->request('POST', ((env('APP_ENV') === 'production') ? env('PRODUCTION_AUTH_URL') : env('STAGING_AUTH_URL')) . 'oauth2/token?grant_type=client_credentials', [
                'headers' => $headers
            ]);

            if ($response->getStatusCode() != 200) {
                return '';
            }

            $response = $response->getBody()->getContents();
            $response = json_decode($response);

            self::updateToken($response->access_token);
            return $response->access_token;
        } catch (ClientException $e) {
            Log::error('***** ERROR API AUTH*****');
            Log::error($e->getMessage());
            Log::error("Data", ['response' => $e->getResponse()->getBody()->getContents(), 'headers' => $headers]);
            return json_encode(['error' => 'Communication error with Dock API']);
        } catch (Exception $e) {
            Log::error('Error obtaining Dock Token ' . $e->getMessage());
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
