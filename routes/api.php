<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

});

