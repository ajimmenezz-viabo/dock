<?php

namespace App\Services;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Middleware\History;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\CurlMultiHandler;

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
            // Handler stack
            $stack = HandlerStack::create();

            // Middleware para history
            $historyContainer = [];
            $history = Middleware::history($historyContainer);
            $stack->push($history);

            $client_options = [
                "handler" => $stack,
            ];
            if (env('PROXY') != null && env('PROXY') != "") {
                $client_options = [
                    RequestOptions::PROXY => [
                        'http'  => env('PROXY'),
                        'https' => env('PROXY')
                    ],
                    RequestOptions::VERIFY => false,
                    RequestOptions::TIMEOUT => 30,
                    "handler" => $stack,
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


            $curl_command = "";
            // Imprime el comando curl
            foreach ($historyContainer as $transaction) {
                $request = $transaction['request'];
                $options = [
                    'cert' => file_get_contents(storage_path('cert/certificate.pem')),
                    'ssl_key' => file_get_contents(storage_path('cert/certificate.key')),
                ];
                $curl_command .= self::generateCurlCommand($request, $options) . PHP_EOL;
            }

            $api_response = json_decode($response->getBody()->getContents());

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                self::saveRequest($url, $method, $authType, $body, json_encode($headers), json_encode($api_response), null, $curl_command);
                return $api_response;
            } else {
                self::saveRequest($url, $method, $authType, $body, json_encode($headers), json_encode($api_response), "HTTP status code: " . $response->getStatusCode(), $curl_command);
                return json_encode(['error' => "Communication error with Dock API"]);
            }
        } catch (ClientException $e) {
            $response = json_decode($e->getResponse()->getBody()->getContents());
            self::saveRequest($url, $method, $authType, $body, json_encode($headers), $e->getResponse()->getBody()->getContents(), $e->getMessage());
            return json_decode(json_encode(['error' => 'Communication error with Dock APIs', "response" => $response->error ?? []]));
        } catch (Exception $e) {
            // self::saveRequest($url, $method, $authType, "", json_encode($headers), null, $e->getMessage());
            return Controller::error('Communication error with Dock API', 500, $e);
        }
    }

    static private function saveRequest($url, $method, $authType, $body, $headers, $response, $error, $curl = "")
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
                'Error' => $error,
                'CurlCommand' => $curl
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

    static private function generateCurlCommand(Request $request, $options = [])
    {
        $method = $request->getMethod();
        $url = (string) $request->getUri();
        $headers = '';

        foreach ($request->getHeaders() as $name => $values) {
            $headers .= '-H "' . $name . ': ' . implode(', ', $values) . '" \\' . PHP_EOL;
        }

        // Obtener el contenido del cuerpo de la solicitud correctamente
        $body = (string) $request->getBody();
        if (!empty($body)) {
            $body = '--data ' . escapeshellarg($body) . ' \\' . PHP_EOL;
        }

        $cert = isset($options['cert']) ? '--cert ' . escapeshellarg($options['cert']) . ' \\' . PHP_EOL : '';
        $sslKey = isset($options['ssl_key']) ? '--key ' . escapeshellarg($options['ssl_key']) . ' \\' . PHP_EOL : '';

        $curlCommand = "curl -X $method \\" . PHP_EOL
            . $headers
            . $body
            . $cert
            . $sslKey
            . "'$url'";

        return $curlCommand;
    }
}
