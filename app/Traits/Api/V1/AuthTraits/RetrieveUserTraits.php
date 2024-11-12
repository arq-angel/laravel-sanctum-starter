<?php

namespace App\Traits\Api\V1\AuthTraits;

use App\Models\RefreshToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

trait RetrieveUserTraits
{
    /**
     * @param string $email
     * @return User|null
     */
    public function getUserByEmail(string $email): User|null
    {
        return User::where('email', $email)->first();
    }

    /**
     * @param string $refreshToken
     * @param string $deviceName
     * @return User
     * @throws AuthenticationException
     */
    public function getUserFromRefreshTokenForCurrentDevice(string $refreshToken, string $deviceName): User
    {
        // Edge case of same device having multiple token is here
        // since the same device refresh tokens are revoked before issuing new tokens for that device this issue should not arise
        // need better implementation in the future

        // Retrieve the refresh token record for the given device name
        $storedRefreshToken = RefreshToken::where('device_name', $deviceName)->first();

        // Check if the refresh token exists and matches
        if (!$storedRefreshToken || !Hash::check($refreshToken, $storedRefreshToken->token)) {
            throw new AuthenticationException('Invalid refresh token.');
        }

        // Check if the token has expired
        if ($storedRefreshToken->expires_at < Carbon::now()) {
            throw new AuthenticationException('Expired refresh token.');
        }

        // Retrieve and return the associated user
        return User::findOrFail($storedRefreshToken->user_id);
    }

    /**
     * @param string $guard
     * @return User|null
     * @throws AuthenticationException
     */
    public function getAuthUserFromSanctum(string $guard = 'sanctum'): User|null
    {
        try {
            $user = Auth::guard($guard)->user();

            if (!$user) {
                throw new AuthenticationException('User is not authenticated.');
            }

            return $user;
        } catch (\Throwable $throwable) {
            throw new AuthenticationException('Failed to retrieve authenticated user.', [], $throwable);
        }
    }

    /**
     * @return null
     */
    public function getUserFromAccessToken(): null
    {
        // it is achieved by using the getAuthUser
        return null;
    }
}
