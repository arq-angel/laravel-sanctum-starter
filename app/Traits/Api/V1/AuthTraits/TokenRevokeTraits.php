<?php

namespace App\Traits\Api\V1\AuthTraits;

use App\Exception\Api\V1\DeviceTokenNotFoundException;
use App\Exception\Api\V1\TokenRevocationException;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait TokenRevokeTraits
{
    /**
     * @param User $user
     * @param string $deviceName
     * @param string $action
     * @return void
     * @throws DeviceTokenNotFoundException
     */
    public function revokeTokensForCurrentDevice(User $user, string $deviceName, string $action = 'login'): void
    {
        $refreshTokenExists = $this->deviceNameExistsInRefreshToken(user: $user, deviceName: $deviceName);
        $accessTokenExists = $this->deviceNameExistsInAccessToken(user: $user, deviceName: $deviceName);

        // Throw an exception if the deviceName does not exist in either token store
        // Check only if its not for login
        if ($action !== 'login' && (!$refreshTokenExists && !$accessTokenExists)) {
            throw new DeviceTokenNotFoundException($deviceName);
        }

        // Revoke previous refresh tokens if they exist
        if ($refreshTokenExists) {
            $this->revokeRefreshTokenForCurrentDevice(user: $user, deviceName: $deviceName);
        }

        // Revoke previous access tokens if they exist
        if ($accessTokenExists) {
            $this->revokeAllAccessTokensForCurrentDevice(user: $user, deviceName: $deviceName);
        }

    }

    /**
     * @param User $user
     * @return array
     */
    public function revokeAllTokensForUser(User $user): array
    {
        // Execute the transaction and store the result
        $revokedRefreshTokens = DB::transaction(function () use ($user) {
            $revokedTokens = $this->revokeAllRefreshTokenForUser(user: $user);
            $this->revokeAllAccessTokensForUser(user: $user);

            return $revokedTokens; // Return the result of the refresh token revocation
        });

        // Return the result of the transaction
        return $revokedRefreshTokens;
    }

    /**
     * @param User $user
     * @param string $deviceName
     * @return bool
     */
    public function deviceNameExistsInAccessToken(User $user, string $deviceName): bool
    {
        try {
            // Check if an access token exists for the specified device name
            return $user->tokens()
                ->where('name', $deviceName)
                ->exists();
        } catch (\Exception $e) {
            // Log the exception and return false
            // Log::error('Error checking device name in access tokens', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * @param User $user
     * @param string $deviceName
     * @return bool
     */
    public function deviceNameExistsInRefreshToken(User $user, string $deviceName): bool
    {
        // Check if the deviceName is associated with the user and refreshToken
        // include metadata such as ip address in the future

        return RefreshToken::where('user_id', $user->id)
            ->where('device_name', $deviceName)
            ->exists();
    }

    /**
     * @param User $user
     * @param string $deviceName
     * @return bool
     * @throws TokenRevocationException
     */
    public function revokeRefreshTokenForCurrentDevice(User $user, string $deviceName): bool
    {
        try {
            // Delete refresh tokens from the database that has the same deviceName
            RefreshToken::where('user_id', $user->id)
                ->where('device_name', $deviceName)
                ->delete();
            return true;
        } catch (\Throwable $throwable) {
            // Log the exception for debugging purposes
            Log::error('Failed to revoke refresh token', [
                'user_id' => $user->id,
                'device_name' => $deviceName,
                'error' => $throwable->getMessage(),
            ]);

            // Throw the exception if you want higher-level handlers to catch it
            throw new TokenRevocationException('Unable to revoke the current refresh token.', 0, $throwable);
        }
    }

    /**
     * @param User $user
     * @return array
     * @throws TokenRevocationException
     */
    public function revokeAllRefreshTokenForUser(User $user): array
    {
        try {
            // Fetch all affected device names before deletion
            $affectedDevices = RefreshToken::where('user_id', $user->id)
                ->pluck('device_name') // Get only the device names
                ->toArray();

            // Check if there are any tokens to delete
            if (empty($affectedDevices)) {
                throw new TokenRevocationException("No refresh tokens found for user ID: {$user->id}");
            }

            // Delete all refresh tokens of the user
            $deletedCount = RefreshToken::where('user_id', $user->id)->delete();

            // Log the action
            Log::info("Revoked {$deletedCount} refresh tokens for user ID: {$user->id}, Devices: " . implode(', ', $affectedDevices));

            // Return count and affected devices
            return [
                'count' => $deletedCount,
                'devices' => $affectedDevices,
            ];
        } catch (\Exception $exception) {
            // Log the error for debugging purposes
            Log::error("Failed to revoke refresh tokens for user ID: {$user->id}. Error: " . $exception->getMessage());

            // Re-throw the exception to handle it further up the call stack if needed
            throw $exception;
        }
    }

    /**
     * @param User $user
     * @param string $deviceName
     * @return bool
     */
    public function revokeAllAccessTokensForCurrentDevice(User $user, string $deviceName): bool
    {
        try {
            // Delete all tokens for the specified device name
            $tokensDeleted = $user->tokens()
                ->where('name', $deviceName)
                ->delete();

            return $tokensDeleted > 0; // Return true if tokens were deleted
        } catch (\Exception $e) {
            // Log the exception and return false
            Log::error('Error revoking tokens for device', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * @param User $user
     * @return bool
     */
    public function revokeAllAccessTokensForUser(User $user): bool
    {
        try {
            // Delete all tokens for the user
            $tokensDeleted = $user->tokens()->delete();

            // Return true if any tokens were deleted
            return $tokensDeleted > 0;
        } catch (\Exception $e) {
            // Log the exception and return false
            Log::error('Error revoking all access tokens for user', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
