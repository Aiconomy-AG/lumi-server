<?php

use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->group(function () {
    Route::post('login', [TokenController::class, 'store'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('logout', [TokenController::class, 'destroy']);
        Route::get('me', [TokenController::class, 'me']);


    });
});

Route::middleware(['auth:sanctum', 'staff', 'admin'])
    ->prefix('v1/admin')
    ->group(function (): void {
        Route::apiResource('users', UserController::class);
    });
