<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\LogoutRequest;
use App\Http\Requests\Api\V1\RefreshTokenRequest;
use App\Traits\Api\V1\ApiResponseTraits;
use App\Traits\Api\V1\AuthTraits\ApiRetrieveUserTraits;
use App\Traits\Api\V1\AuthTraits\ApiValidateUserTraits;
use App\Traits\Api\V1\AuthTraits\AuthControllerTraits;
use App\Traits\Api\V1\AuthTraits\TokenCreateTraits;
use App\Traits\Api\V1\AuthTraits\TokenRevokeTraits;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;


class AuthController extends Controller
{

    use ApiResponseTraits, TokenCreateTraits, TokenRevokeTraits, ApiRetrieveUserTraits, ApiValidateUserTraits, AuthControllerTraits;

    /**
     * using class-level variables for this purpose can introduce issues in a shared or multi-threaded environment
     * so i can't use
     * private $success,
     * private $user and
     * private $response here
     */


    /**
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $success = false;
        $user = null;
        $response = null;

        try {
            // Step 1: Retrieve the validated user
            $user = $this->getCredentialValidatedUser(email: $request->email, password: $request->password);

            // Step 2: Revoke previous tokens for the device
            $this->revokeTokensForCurrentDevice($user, $request->deviceName);

            // Step 3: Generate new tokens
            // Create Access Token
            $accessToken = $this->createAccessToken(user: $user, deviceName: $request->deviceName);
            // Generate Refresh Token
            $refreshToken = $this->generateRefreshToken(user: $user, deviceName: $request->deviceName);

            // Step 4: Prepare success response
            $response = $this->successResponse(
                message: 'Login successful. Token Generated.',
                data: [
                    'accessToken' => $accessToken->plainTextToken,
                    'refreshToken' => $refreshToken['refreshToken'],
                    'expiresIn' => $this->getAccessTokenExpiryTime(),
                    'deviceName' => $refreshToken['deviceName'],
                ]
            );

            // Step 5: Mark operation as successful
            $success = true;
        } catch (\Throwable $throwable) {
            // Step 6: Handle exceptions
            $response = $this->errorResponse(
                message: 'Error occurred during authentication!',
                exception: $throwable,
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } finally {
            // Step 7: Log the login attempt
            Log::info('Login attempted,', [
                'email' => $user->email ?? 'Unknown',
                'result' => $success ? 'success' : 'failure',
            ]);
        }

        // Step 8: Return the response
        return $response;
    }

    /**
     * @return JsonResponse
     */
    public function getLoggedInDevices(): JsonResponse
    {
        $success = false;
        $user = null;
        $response = null;

        try {
            // Step 1: Retrieve the authenticated user
            $user = $this->getAuthUserFromSanctum();

            // Step 2: Retrieve the devices currently logged in
            $loggedInDevices = $this->getLoggedInDevicesList($user);

            // Step 3: Prepare success response
            $response = $this->successResponse(
                message: 'Logged in devices retrieved successfully.',
                data: [
                    "count" => $loggedInDevices['count'],
                    "devices" => $loggedInDevices['devices'],
                ],
            );

            // Step 4: Mark operation as successful
            $success = true;
        } catch (\Throwable $throwable) {
            // Step 5: Handle exceptions
            $response = $this->errorResponse(
                message: 'An error occurred during logout!',
                exception: $throwable,
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        } finally {
            // Step 6: Log the login attempt
            Log::info('Retrieve logged in devices list attempted,', [
                'email' => $user->email ?? 'Unknown',
                'result' => $success ? 'success' : 'failure',
            ]);
        }

        return $response;
    }

    /**
     * @param LogoutRequest $request
     * @return JsonResponse
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        $success = false;
        $user = null;
        $response = null;

        try {
            // Step 1: Retrieve the authenticated user
            $user = $this->getAuthUserFromSanctum();

            // Step 2: Revoke previous tokens for the device
            $this->revokeTokensForCurrentDevice($user, $request->deviceName, action: 'logout');

            // Step 3: Prepare success response
            $response = $this->successResponse(
                message: "Log out successful.",
                data: [
                    'deviceName' => $request->deviceName,
                ]
            );

            // Step 4: Mark operation as successful
            $success = true;
        } catch (\Throwable $throwable) {
            // Step 5: Handle exceptions
            $response = $this->errorResponse(
                message: 'An error occurred during logout!',
                exception: $throwable,
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        } finally {
            // Step 6: Log the login attempt
            Log::info('Log out attempted,', [
                'email' => $user->email ?? 'Unknown',
                'result' => $success ? 'success' : 'failure',
            ]);
        }

        return $response;
    }

    /**
     * @return JsonResponse
     */
    public function logOutFromAll(): JsonResponse
    {
        $success = false;
        $user = null;
        $response = null;

        try {
            //Step 1: Retrieve the authenticated user
            $user = $this->getAuthUserFromSanctum();

            //Step 2: Revoke all refresh and access tokens for the user
            $data = $this->revokeAllTokensForUser($user);

            // Step 3: Prepare success response
            $response = $this->successResponse(
                message: 'Log out from all sessions successful.',
                data: [
                    'tokensRevoked' => [
                        'count' => $data['count'] ?? null,
                        'devices' => $data['devices'] ?? null,
                    ],
                ]
            );

            // Step 4: Mark operation as successful
            $success = true;
        } catch (\Throwable $throwable) {
            // Step 5: Handle exceptions
            $response = $this->errorResponse(
                message: 'An error occurred during logout from all sessions!',
                exception: $throwable,
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        } finally {
            // Step 6: Log the login attempt
            Log::info('Retrieve logged in devices list attempted,', [
                'email' => $user->email ?? 'Unknown',
                'result' => $success ? 'success' : 'failure',
            ]);
        }

        return $response;
    }

    /**
     * @param RefreshTokenRequest $request
     * @return JsonResponse
     */
    public function refreshToken(RefreshTokenRequest $request)
    {
        $success = false;
        $user = null;
        $response = null;

        try {

            // Step 1: Retrieve the user using refresh token of the current device
            $user = $this->getUserFromRefreshTokenForCurrentDevice(refreshToken: $request->refreshToken, deviceName: $request->deviceName);

            // Step 2: Revoke previous tokens for the device
            $this->revokeTokensForCurrentDevice($user, $request->deviceName);

            // Step 3: Generate new tokens
            // Create Access Token
            $newAccessToken = $this->createAccessToken(user: $user, deviceName: $request->deviceName);
            // Generate Refresh Token
            $newRefreshToken = $this->generateRefreshToken(user: $user, deviceName: $request->deviceName);

            // Step 4: Prepare success response
            $response = $this->successResponse(
                message: 'New token generation successful.',
                data: [
                    'accessToken' => $newAccessToken->plainTextToken,
                    'refreshToken' => $newRefreshToken['refreshToken'],
                    'expiresIn' => $this->getAccessTokenExpiryTime(),
                    'deviceName' => $newRefreshToken['deviceName'],
                ],
            );

        } catch (\Throwable $throwable) {
            // Step 6: Handle exceptions
            $response = $this->errorResponse(
                message: 'Error occurred during authentication!',
                exception: $throwable,
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } finally {
            // Step 7: Log the login attempt
            Log::info('Login attempted,', [
                'email' => $user->email ?? 'Unknown',
                'result' => $success ? 'success' : 'failure',
            ]);
        }

        // Step 8: Return the response
        return $response;
    }

}
