<?php

namespace App\Http\Middleware\Api\V1;

use App\Models\RefreshToken;
use App\Traits\Api\V1\AuthTraits\RetrieveUserTraits;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class VerifyRefreshToken
{
    use RetrieveUserTraits;

    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Step 1: Validate the incoming request
        $request->validate([
            'refreshToken' => ['required', 'string'],
            'deviceName' => ['required', 'string', 'max:248'],
        ]);

        // Step 2: Initialize response structure
        $response = [
            'isSuccess' => false,
            'message' => 'Token validation failed!',
            'data' => [
                'deviceName' => $request->deviceName,
            ]
        ];

        // Step 3: Retrieve the refresh token record for the given device name
        /**
         * This ensures that the user can only refresh login from the same device
         * need to do implementation to approve login for another device
         */
        $storedRefreshToken = RefreshToken::where('device_name', $request->deviceName)->first();

        // Step 4: Validate the token's existence and hash
        if (!$storedRefreshToken || !Hash::check($request->refreshToken, $storedRefreshToken->token)) {
            $response['message'] = 'Invalid refresh token!';
            return response()->json($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Step 5: Validate that the token is not expired
        if ($storedRefreshToken->expires_at < Carbon::now()) {
            $response['message'] = 'Expired refresh token!';
            return response()->json($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Step 6: Proceed with the next middleware or request handler
        return $next($request);
    }

}
