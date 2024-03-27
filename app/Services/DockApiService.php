<?php

namespace App\Services;

use App\Http\Controllers\Caradhras\Auth\TokenController;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Message;
use Exception;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\RequestOptions;
use PhpParser\Node\Expr\Throw_;

class DockApiService
{
    static public function request($url, $method, $params = [], $headers = [], $authType, $raw = null)
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

            $headers = array_merge($headers, self::setAuthHeaders($authType));

            if ($method == 'GET') {
                $url .= '?' . http_build_query($params);
            } else {
                $body = json_encode($params);
            }

            if ($raw != null)
                $body = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            $response = $client->request($method, $url, [
                'headers' => $headers,
                'body' => $body ?? null
            ]);

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                return json_decode($response->getBody()->getContents());
            } else {
                Log::error('Communication error with Dock API ' . $response->getBody()->getContents());
                return json_encode(['error' => 'Communication error with Dock API']);
            }
        } catch (ClientException $e) {
            Log::error('***** ERROR API *****');
            Log::error($e->getMessage());
            Log::error("Data", ['response' => $e->getResponse()->getBody()->getContents()]);
            return json_encode(['error' => 'Communication error with Dock API']);
        } catch (Exception $e) {
            Log::error('Communication error with Dock API ' . $e->getMessage());
            return json_encode(['error' => 'Communication error with Dock API']);
        }
    }

    static private function setAuthHeaders($authType)
    {
        $base_headers = [
            'Accept' => 'application/json, application/xml, multipart/form-data',
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
