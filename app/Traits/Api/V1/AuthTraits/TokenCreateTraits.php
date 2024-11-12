<?php

namespace App\Traits\Api\V1\AuthTraits;

use App\Models\RefreshToken;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

trait TokenCreateTraits
{
    /**
     * @param User $user
     * @param string|null $deviceName
     * @return NewAccessToken
     */
    public function createAccessToken(User $user, string $deviceName = null): NewAccessToken
    {
        // Create a personal access token for the user
        $token = $user->createToken($deviceName);

        // set expiry for newly created access token - validated by sanctum middleware when used with requests
        $this->setAccessTokenExpiration($token);

        return $token;
    }

    /**
     * @return int
     */
    public function getAccessTokenExpiryTime(): int
    {
        // Get Sanctum's default expiration time (in minutes) or fallback to 60 minutes
        // Return value converted to seconds for consistency
        // Can be changed by changing expiration in Config/sanctum
        return config('sanctum.expiration', 60) * 60;
    }

    /**
     * @param $token
     * @return void
     * @throws Exception
     */
    public function setAccessTokenExpiration($token): void
    {
        // Safeguard to ensure token object has the required structure
        if ($token->accessToken) {
            $token->accessToken->expires_at = now()->addSeconds($this->getAccessTokenExpiryTime());
            $token->accessToken->save();
        } else {
            throw new Exception('Access token object is not properly initialized.');
        }
    }

    /**
     * @param User $user
     * @param string $deviceName
     * @return array
     */
    public function generateRefreshToken(User $user, string $deviceName): array
    {
        $refreshToken = Str::random(64);
        $refreshTokenExpiry = Carbon::now()->addDays(30); // Refresh token expires in 30 days

        $data = RefreshToken::create([
            'user_id' => $user->id,
            'token' => Hash::make($refreshToken), // Laravel built in hash method that uses sha256 hashing
            'expires_at' => $refreshTokenExpiry,
            'device_name' => $deviceName,
        ]);

        return [
            'refreshToken' => $refreshToken, // this is non-hashed token to be sent to the user
            'deviceName' => $data->device_name,
        ];
    }

    /**
     * @param string $deviceName
     * @param string $hashedToken
     * @return RefreshToken|null
     */
    public function retrieveStoredRefreshToken(string $deviceName, string $hashedToken): RefreshToken|null
    {
        return RefreshToken::where('device_name', $deviceName)
            ->where('token', $hashedToken)
            ->first();
    }

    /**
     * @param string $deviceName
     * @param string $refreshToken
     * @return bool
     */
    public function validateRefreshToken(string $deviceName, string $refreshToken): bool
    {
        // Retrieve the stored hashed token from the database
        $refreshTokenRecord = RefreshToken::where('device_name', $deviceName)
            ->where('expires_at', '>', now()) // Check if the token is not expired
            ->first();

        if (!$refreshTokenRecord) {
            return false; // Token not found or expired
        }

        // Compare the received token with the hashed token
        if (Hash::check($refreshToken, $refreshTokenRecord->token)) {
            return true; // Token is valid
        }

        return false; // Token is invalid
    }

}
