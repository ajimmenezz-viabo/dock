<?php

namespace App\Exceptions;

use Ramsey\Uuid\Uuid;
use Exception;

class AuthorizationException extends Exception
{
    protected $uuid;
    protected $description;
    protected $code;

    public function __construct($message = 'Unauthorized', $code = 401, $error_code = 500)
    {
        parent::__construct($message, $code);
        $this->uuid = Uuid::uuid7()->toString();
        $this->description = $message;
        $this->code = $error_code;
    }

    public function getError()
    {
        return [
            'error' => [
                'id' => $this->uuid,
                'description' => $this->description,
                'code' => $this->code
            ]
        ];
    }
}
