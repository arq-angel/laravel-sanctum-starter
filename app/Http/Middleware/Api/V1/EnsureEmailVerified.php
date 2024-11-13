<?php

namespace App\Http\Middleware\Api\V1;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        $user = User::where('email', $request->email)->first();
        if (!$user || ($user instanceof MustVerifyEmail && !$user->hasVerifiedEmail())) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Email is not verified.',
            ], Response::HTTP_CONFLICT);
        }

        return $next($request);
    }
}
