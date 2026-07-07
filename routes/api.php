<?php

use App\Http\Controllers\Auth\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Workspace\Http\Controllers\AuthController;
use Modules\Workspace\Http\Controllers\TimeTrackingController;

Route::post('v1/workspace/auth/login', [AuthController::class, 'login']);

Route::prefix('auth')->group(function () {
    Route::post('login', [TokenController::class, 'store'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('logout', [TokenController::class, 'destroy']);
    });

    Route::get('profile', [AuthController::class, 'profile']);

    Route::prefix('tasks/{taskId}')->group(function (): void {
        Route::post('time-entries/start', [TimeTrackingController::class, 'start']);
        Route::post('time-entries/{entryId}/stop', [TimeTrackingController::class, 'stop']);
        Route::get('time-entries', [TimeTrackingController::class, 'index']);
    });
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
