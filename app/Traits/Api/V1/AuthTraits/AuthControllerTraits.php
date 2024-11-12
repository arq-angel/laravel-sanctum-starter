<?php

namespace App\Traits\Api\V1\AuthTraits;

use App\Models\RefreshToken;
use App\Models\User;

trait AuthControllerTraits
{
    /**
     * @param User $user
     * @return array
     * @throws \Throwable
     */
    public function getLoggedInDevicesList(User $user): array
    {
        try {
            $deviceNames = RefreshToken::select('device_name')->where('user_id', $user->id)->get()->toArray();
            $noOfDevices = count($deviceNames);

            return [
                'devices' => $deviceNames,
                'count' => $noOfDevices,
            ];
        } catch (\Throwable $throwable) {
            // Immediately throwing the exception at the moment to be caught by requesting method catch block
            throw $throwable;
        }
    }
}
