<?php

use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::prefix('auth')->group(function () {
    Route::post('login', [TokenController::class, 'store'])->middleware('throttle:auth');
    
    Route::get('reset-password/validate', [PasswordResetController::class, 'validateToken'])
    ->middleware('throttle:auth');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('logout', [TokenController::class, 'destroy']);
        Route::get('me', [TokenController::class, 'me']);
        Route::patch('me/status', [TokenController::class, 'updateStatus']);

    });
});

Route::middleware(['auth:sanctum', 'staff', 'admin'])
    ->prefix('v1/admin')
    ->group(function (): void {
        Route::apiResource('users', UserController::class);
        Route::post('users/{userId}/resend-invite', [UserController::class, 'resendInvite']);
    });
