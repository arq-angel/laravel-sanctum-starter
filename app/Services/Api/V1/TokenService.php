<?php

namespace App\Services\Api\V1;

use App\Models\User;

class TokenService
{
    public function revokeAllOldTokens(User $user): void
    {
        $user->tokens()->delete(); // Revokes all old tokens
    }
}
