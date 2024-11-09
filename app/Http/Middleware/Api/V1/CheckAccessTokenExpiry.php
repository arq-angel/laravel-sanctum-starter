<?php

namespace App\Http\Middleware\Api\V1;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAccessTokenExpiry
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sanctum handles this middleware's purpose by returning Unauthenticated. message with 401 status code when the token is expired
        // We can combine these two middleware to create a middleware called AuthenticateAndCheckExpiry

        // Authenticate the user using sanctum
        $user = Auth::guard('sanctum')->user();

        // If no user is authenticated, return a 401 error
        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Invalid or expired access token.',
                'data' => []
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Debug or allow the request to proceed
        return $next($request);
    }
}
