<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Exception;

class Controller extends BaseController
{
    public $encrypter;

    public function __construct()
    {
        $this->encrypter = new \Illuminate\Encryption\Encrypter(env('APP_KEY'), 'AES-256-CBC');
    }


    static public function error($message, $code = 500, Exception $e)
    {
        if (env('DEV_MODE') !== 'false')
            return response()->json(['message' => $message, 'error_dev' => self::dev_error($e)], $code);

        return response()->json(['message' => $message], $code);
    }


    static public function dev_error(Exception $e)
    {
        return [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
            'trace' => $e->getTraceAsString()
        ];
    }

    static public function toUnixTime($date)
    {
        return strtotime($date);
    }

    static public function encrypt($data)
    {
        $encrypter = new \Illuminate\Encryption\Encrypter(env('APP_KEY'), 'AES-256-CBC');
        return $encrypter->encrypt($data);
    }

    static public function decrypt($data)
    {
        $encrypter = new \Illuminate\Encryption\Encrypter(env('APP_KEY'), 'AES-256-CBC');
        return $encrypter->decrypt($data);
    }
    
}
