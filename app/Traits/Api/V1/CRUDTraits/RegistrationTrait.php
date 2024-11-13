<?php

namespace App\Traits\Api\V1\CRUDTraits;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;

trait RegistrationTrait
{
    public function isEmailVerificationRequired($user): bool
    {
        if ($user instanceof User) {
            $user = new User();
        }

        if ($user instanceof MustVerifyEmail) {
            return true;
        }

        return false;
    }
}
