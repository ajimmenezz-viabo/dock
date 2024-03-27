<?php

namespace App\Http\Controllers\Caradhras\Security;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use App\Services\DockApiService;

class RsaController extends Controller
{
    public function generate()
    {
        try {
            $privateKey = openssl_pkey_new([
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
                "digest_alg" => "sha256"
            ]);

            openssl_pkey_export($privateKey, $privateKeyPEM, null, [
                "PKCS8" => true,
            ]);

            $publicKey = openssl_pkey_get_details($privateKey);
            $publicKeyPEM = $publicKey["key"];

            echo "Private Key: \n";
            echo $privateKeyPEM;
            echo "\n\n";
            echo base64_encode($privateKeyPEM);
            echo "\n\n";
            echo "Public Key: \n";
            echo $publicKeyPEM;
            echo "\n\n";
            echo base64_encode($publicKeyPEM);

            // return response()->json([
            //     'private_key' => [
            //         'pem' => $privateKeyPEM,
            //         'encoded' => base64_encode($privateKeyPEM)
            //     ],
            //     'public_key' => [
            //         'pem' => $publicKeyPEM,
            //         'encoded' => base64_encode($publicKeyPEM)
            //     ]
            // ]);
            
        } catch (Exception $e) {
            return self::error('Error generating RSA keys', 400, $e);
        }
    }

    public function upload(Request $request)
    {
        try {

            $dockRaw = [
                'key' => $request['key'],
                'name' => $request['name']
            ];

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/client',
                'POST',
                [],
                [],
                'bearer',
                $dockRaw
            );

            return response()->json($response, 200);


        } catch (Exception $e) {
            return self::error('Error uploading RSA keys', 400, $e);
        }
    }

    public function update(Request $request){
        try {

            $dockRaw = [
                'public_key' => $request['key'],
                'name' => $request['name']
            ];

            $response = DockApiService::request(
                ((env('APP_ENV') === 'production') ? env('PRODUCTION_URL') : env('STAGING_URL')) . 'cards/v1/client',
                'PATCH',
                [],
                [],
                'bearer',
                $dockRaw
            );

            return response()->json($response, 200);
        } catch (Exception $e) {
            return self::error('Error updating RSA keys', 400, $e);
        }
    }
}
