<?php

use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use Modules\Workspace\Http\Controllers\TimeTrackingController;

Route::prefix('auth')->group(function () {
    Route::post('login', [TokenController::class, 'store'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('logout', [TokenController::class, 'destroy']);
        Route::get('me', [TokenController::class, 'me']);

        Route::prefix('tasks/{taskId}')->group(function (): void {
            Route::post('time-entries/start', [TimeTrackingController::class, 'start']);
            Route::post('time-entries/{entryId}/stop', [TimeTrackingController::class, 'stop']);
            Route::get('time-entries', [TimeTrackingController::class, 'index']);
        });
    });
});

Route::middleware(['auth:sanctum', 'staff', 'admin'])
    ->prefix('v1/admin')
    ->group(function (): void {
        Route::apiResource('users', UserController::class);
    });
