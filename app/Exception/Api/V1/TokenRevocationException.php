<?php

namespace App\Exception\Api\V1;

use Exception;

class TokenRevocationException extends Exception
{
    public function __construct(string $message = "", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
