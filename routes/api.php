<?php

use App\Http\Controllers\Auth\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [TokenController::class, 'store'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('logout', [TokenController::class, 'destroy']);
    });
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
