<?php

namespace App\Exception\Api\V1;

use Exception;

class ImageUploadFailedException extends Exception
{
    public function __construct($message = "", $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
