<?php

use App\Http\Controllers\Api\V1\AuthController;
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

    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

    /** Sanctum protected routes */
    Route::group(['middleware' => ['auth:sanctum']], function () {
        Route::get('/protected', function (Request $request) {
            return response()->json([
                'isSuccess' => true,
                'message' => 'Protected!',
                'request' => $request->all(),
                'userName' => $request->user()->name,
            ]);
        });

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-from-all', [AuthController::class, 'logoutFromAll']);

    });

});

