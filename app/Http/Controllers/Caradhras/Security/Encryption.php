<?php

namespace App\Http\Controllers\Caradhras\Security;

use Exception;

class Encryption
{
    public static function encrypt($data, $key)
    {
    }

    public static function decrypt($aesBase64, $ivBase64, $txtBase64)
    {
        try {
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
            $aesKey = base64_decode($aesBase64);

            // Desencriptar la clave AES con RSA
            $decryptedAESKey = null;
            $result = openssl_private_decrypt($aesKey, $decryptedAESKey, $privateKey, OPENSSL_NO_PADDING);
            if ($result === false) {
                die("Error al desencriptar la clave AES" . openssl_error_string());
            }

            // Decodificar el mensaje encriptado desde Base64
            $msg = base64_decode($txtBase64);

            // Desencriptar el mensaje con AES
            $iv = base64_decode($ivBase64);

            $output = openssl_decrypt($msg, "aes-256-gcm", $decryptedAESKey, OPENSSL_RAW_DATA, $iv);
            if ($output === false) {
                die("Error al desencriptar el mensaje ". openssl_error_string());
            }
            return response()->json([
                'decrypted_text' => $output,
            ]);
        } catch (Exception $e) {
            abort(400, 'Error decrypting data ' . $e->getMessage());
        }
    }
}
