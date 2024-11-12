<?php

use App\Http\Middleware\Api\V1\AuthenticateAndCheckExpiry;
use App\Http\Middleware\Api\V1\CheckAccessTokenExpiry;
use App\Http\Middleware\Api\V1\VerifyRefreshToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

            /** The following validate various checks before passing to appropriate request classes or controllers */
            'verify.refresh.token' => VerifyRefreshToken::class,

        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom handler for AuthenticationException
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

        // Custom handler for ThrottleRequestsException
        $exceptions->render(function (ThrottleRequestsException $exception, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Too Many Attempts.',
                    'debug' => [
                        'error' => $exception->getMessage(),
                        'trace' => $exception->getTrace(),
                    ]
                ], 404);
            }

            // For web routes, use the default behavior
            return abort(404, 'Resource not found.');
        });

        // Handler for exception uncaught by errorResponse method -  to be implemented in future optimization
        $exceptions->render(function (Throwable $throwable, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Uncaught Exception - needs to be handled.',
                    'debug' => [
                        'error' => $throwable->getMessage(),
                        'trace' => $throwable->getTrace(),
                    ]
                ], 404);
            }

            // For web routes, use the default behavior
            return abort(404, 'Resource not found.');
        });

        /**
         * Custom Handler need to be created for
         * AccessDeniedHttpException,
         *
         */

    })->create();
