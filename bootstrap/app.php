<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,

            /** The following two middlewares are here for demonstration and learning purposes but no necessary anymore because i am using another approach */
            'check.access.token.expiry' => \App\Http\Middleware\Api\V1\CheckAccessTokenExpiry::class,
            'auth.check.expiry' => \App\Http\Middleware\Api\V1\AuthenticateAndCheckExpiry::class,


        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $exception, $request) {
            // Check if the request expects a JSON response, we have to include Accept: "application/json" in all requests that is used for login or needs authentication
            if ($request->expectsJson()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => $exception->getMessage(),
                ], 401);
            }

            // For web routes, use the default behavior
            return redirect()->guest(route('login'));
        });

    })->create();
