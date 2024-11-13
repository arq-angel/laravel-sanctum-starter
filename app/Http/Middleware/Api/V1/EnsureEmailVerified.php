<?php

namespace App\Http\Middleware\Api\V1;

use App\Models\RefreshToken;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        $user = null;

        if ($request->has(['email', 'password',])) {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => __('Invalid credentials.'),
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!$user->hasVerifiedEmail()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => __('Access denied.'),
                ], Response::HTTP_FORBIDDEN);
            }
        } elseif ($request->has(['refreshToken', 'deviceName'])) {
            $refreshToken = RefreshToken::where('device_name', $request->deviceName)->first();

            if ($refreshToken && Hash::check($request->refreshToken, $refreshToken->token)) {
                $user = $refreshToken->user; // Retrieve the user via Eloquent relationship
            }

            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Invalid credentials.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Invalid credentials.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user instanceof MustVerifyEmail && !$user->hasVerifiedEmail()) {
            return response()->json([
                'isSuccess' => false,
                // 'message' => 'Email is not verified.',   // commented, to protect against enumeration
                'message' => 'Access denied.',
            ], Response::HTTP_CONFLICT);
        }

        return $next($request);
    }
}
