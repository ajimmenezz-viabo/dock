<?php

namespace App\Services;

use App\Http\Controllers\Caradhras\Auth\TokenController;
use Exception;
use Illuminate\Support\Facades\Log;

class DockApiService
{


    static public function request($url, $method, $params = [], $headers = [], $authType)
    {
        try {
            $client = new \GuzzleHttp\Client();

            $headers = array_merge($headers, self::setAuthHeaders($authType));

            if ($method == 'GET') {
                $url .= '?' . http_build_query($params);
            } else {
                $body = json_encode($params);
            }

            $response = $client->request($method, $url, [
                'headers' => $headers,
                'body' => $body ?? null
            ]);


            if ($response->getStatusCode() == 200) {
                return json_encode(json_decode($response->getBody()));
            } else {
                Log::error('Communication error with Dock API ' . $response->getBody());
                return json_encode(['error' => 'Communication error with Dock API']);
            }
        } catch (Exception $e) {
            Log::error('Communication error with Dock API ' . $e->getMessage());
            return json_encode(['error' => 'Communication error with Dock API']);
        }
    }

    static private function setAuthHeaders($authType)
    {
        $base_headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];

        switch ($authType) {
            case 'basic':
                $base_headers['Authorization'] = 'Basic ' . base64_encode(env('DOCK_API_CLIENT_U') . ':' . env('DOCK_API_CLIENT_P'));
                break;
            case 'bearer':
                $base_headers['Authorization'] = 'Bearer ' . TokenController::get();
                break;
        }

        return $base_headers;
    }
}
