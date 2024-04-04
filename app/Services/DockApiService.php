<?php

namespace App\Services;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;

use App\Http\Controllers\Caradhras\Auth\TokenController;
use App\Http\Controllers\Controller;
use App\Models\Shared\DockRequests;
use Illuminate\Support\Facades\Log;

use Exception;

class DockApiService
{
    static public function request($url, $method, $params = [], $headers = [], $authType, $raw = null)
    {
        try {
            $client_options = [];
            if (env('APP_ENV') !== 'production') {
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
                $body = "";
            } else {
                $body = json_encode($params);
            }

            if ($raw != null)
                $body = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            $response = $client->request($method, $url, [
                'headers' => $headers,
                'cert' => storage_path('cert/certificate.pem'),
                'ssl_key' => storage_path('cert/certificate.key'),
                'body' => $body ?? ""
            ]);

            $api_response = json_decode($response->getBody()->getContents());

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {

                self::saveRequest($url, $method, $authType, $body, json_encode($headers), json_encode($api_response), null);
                return $api_response;
            } else {
                self::saveRequest($url, $method, $authType, $body, json_encode($headers), json_encode($api_response), "HTTP status code: " . $response->getStatusCode());
                return json_encode(['error' => 'Communication error with Dock API']);
            }
        } catch (ClientException $e) {
            self::saveRequest($url, $method, $authType, $body, json_encode($headers), $e->getResponse()->getBody()->getContents(), $e->getMessage());
            return json_encode(['error' => 'Communication error with Dock API']);
        } catch (Exception $e) {
            // self::saveRequest($url, $method, $authType, "", json_encode($headers), null, $e->getMessage());
            return Controller::error('Communication error with Dock API', 500, $e);
        }
    }

    static private function saveRequest($url, $method, $authType, $body, $headers, $response, $error)
    {
        try {
            Log::info('Dock API Request', [
                'Endpoint' => $url,
                'Method' => $method,
                'AuthType' => $authType,
                'Body' => $body,
                'Headers' => $headers,
                'Response' => $response,
                'Error' => $error
            ]);

            DockRequests::create([
                'Endpoint' => $url,
                'Method' => $method,
                'AuthType' => $authType,
                'Body' => $body,
                'Headers' => $headers,
                'Response' => $response,
                'Error' => $error
            ]);
        } catch (Exception $e) {
            
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
