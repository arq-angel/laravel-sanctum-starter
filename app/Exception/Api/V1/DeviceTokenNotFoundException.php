<?php

namespace App\Exception\Api\V1;

use Exception;

class DeviceTokenNotFoundException extends Exception
{
    public function __construct(string $deviceName)
    {
        parent::__construct("No tokens found for the device:, $deviceName");
    }
}
