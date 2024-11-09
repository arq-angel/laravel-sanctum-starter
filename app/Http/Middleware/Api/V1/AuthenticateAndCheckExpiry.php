<?php

namespace App\Http\Middleware\Api\V1;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAndCheckExpiry
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // While this method does demonstrate that we can use this to check expiry and verify authentication - its better to modify the sanctum's
        // AuthenticationException handling in bootstrap/app.php


        // Extract the Bearer token from the Authorization header
        $authorizationHeader = $request->header('Authorization');
        $tokenString = $this->getBearerToken($authorizationHeader);

        if (!$tokenString) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Token not provided.',
                'data' => []
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Use Sanctum's built-in method to validate the token
        $token = PersonalAccessToken::findToken($tokenString);

        // Check if token exists and is not expired
        if (!$token || Carbon::now()->greaterThan($token->expires_at)) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Invalid or expired access token.',
                'data' => []
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Since the above steps already verify the authentication status as well the following steps is mostly to bind the user with the $request

        // Attempt to authenticate the user via Sanctum
        $user = Auth::guard('sanctum')->user();

        // If no authenticated user, return unauthenticated response
        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthenticated.',
                'data' => []
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Bind the authenticated user to the request
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Proceed to the next middleware or controller
        return $next($request);
    }

    /**
     * Extract the Bearer token from the Authorization header.
     */
    private function getBearerToken($header): ?string
    {
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }
}
