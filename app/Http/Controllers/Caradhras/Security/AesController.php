<?php

namespace App\Http\Controllers\Caradhras\Security;

use App\Http\Controllers\Controller;
use Exception;
use App\Services\DockApiService;

class AesController extends Controller
{
    public function generate()
    {
        try {

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/client/key/generate',
                'POST',
                [],
                [],
                'bearer'
            );

            return response()->json($response, 200);
        } catch (Exception $e) {
            return self::error('Error generating AES Key', 400, $e);
        }
    }

    public function find()
    {
        try {
            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/client/key',
                'GET',
                [],
                [],
                'bearer'
            );

            // Cargar la clave privada RSA desde una variable de entorno
            $pkBase64 = env('PK_RSA');
            if ($pkBase64 === false) {
                die("Error: Variable de entorno 'PK_RSA' no definida");
            }

            // Decodificar la clave privada RSA de Base64
            $pk = base64_decode($pkBase64);

            // Leer la clave privada RSA desde la cadena PEM
            $privateKey = openssl_pkey_get_private($pk);
            if ($privateKey === false) {
                die("Error al decodificar la clave privada");
            }

            // Decodificar la clave AES y el IV de Base64
            $aesKey = base64_decode($response->key);

            // Desencriptar la clave AES con RSA
            $decryptedAESKey = null;
            $result = openssl_private_decrypt($aesKey, $decryptedAESKey, $privateKey, OPENSSL_NO_PADDING);
            if ($result === false) {
                die("Error al desencriptar la clave AES " . openssl_error_string());
            }

            echo "decryptedAESKey: " . $decryptedAESKey . "\n";

            // return response()->json($response, 200);
        } catch (Exception $e) {
            return self::error('Error Getting AES Key', 400, $e);
        }
    }
}
