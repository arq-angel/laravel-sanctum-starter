<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\UserController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

/** api/v1 */
Route::group(['prefix' => '/v1', 'namespace' => 'App\Http\Controllers\Api\V1', 'middleware' => []], function () {

    /** Demo routes start */
    Route::get('/hello', function (Request $request) {
        return response()->json([
            'isSuccess' => true,
            'message' => 'Hello World!',
            'request' => $request->all()
        ]);
    });
    Route::post('/sanctum/token', function (Request $request) {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:248'],
            'password' => 'required',
            'device_name' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'isSuccess' => false,
                'message' => 'These credentials do not match our records.',
                'errors' => [
                    'email' => ['The provided credentials are incorrect.'],
                ]
            ]);
        }

        return $user->createToken($request->device_name)->plainTextToken;
    });
    /** Demo routes end */

    /** Route to authenticate and receive the accessToken and refreshToken */
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1', 'email.verified');

    /** Middleware to verify if the refresh token exists in the refresh_tokens table before executing other actions */
    Route::group(['middleware' => ['verify.refresh.token',]], function () {
        /** Route to refresh the accessToken using refreshToken */
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    });

    /** Users need to register before being logged in */
    Route::post('/user', [UserController::class, 'store']);

    /** Route to verify email with token */
    Route::post('/email/verify', [EmailVerificationController::class, 'verify']);

    /** Route to request verification link */
    Route::get('/email/send-verification', [EmailVerificationController::class, 'sendVerificationEmail']);

    /** Sanctum protected routes */
    Route::group(['middleware' => ['auth:sanctum', 'verified']], function () {

        /** Throttled routes */
        Route::group(['middleware' => 'throttle:6,1'], function () {
            /** Demo route to check authentication */
            Route::get('/protected', function (Request $request) {
                return response()->json([
                    'isSuccess' => true,
                    'message' => 'Protected!',
                    'request' => $request->all(),
                    'userName' => $request->user()->name,
                ]);
            });

            /** Route to get the list of logged in devices */
            Route::get('/logged-in-devices', [AuthController::class, 'getLoggedInDevices']);

            /** Route to log out from current session only */
            Route::post('/logout', [AuthController::class, 'logout']);

            /** Route to log out from all sessions */
            Route::post('/logout-from-all', [AuthController::class, 'logoutFromAll']);
        });

        /** Route to perform user CRUD actions */
        /** preventing logged in users from registering new user profile or abusing the system */
        Route::apiResource('/user', UserController::class)->except(['store', 'show']);


    });

});

