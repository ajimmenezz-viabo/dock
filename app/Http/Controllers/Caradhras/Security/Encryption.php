<?php

namespace App\Http\Controllers\Caradhras\Security;

use Exception;

class Encryption
{

    public static function decrypt($aesBase64, $ivBase64, $msgBase64)
    {
        $goFilePath = realpath(__DIR__ . '/DecryptGo/decrypt.go');

        // Construir el comando con los parámetros escapados
        $command = "go run " . $goFilePath . " " .
            escapeshellarg(env('RSA_PRIVATE_KEY')) . " " .
            escapeshellarg($aesBase64) . " " .
            escapeshellarg($ivBase64) . " " .
            escapeshellarg($msgBase64);

        $escapedCommand = escapeshellcmd($command);

        $output = exec($escapedCommand);

        // var_dump($output);

        // Devolver el resultado obtenido del programa Go
        return trim($output);
    }
}
