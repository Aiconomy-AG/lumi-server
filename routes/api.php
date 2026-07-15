<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::prefix('auth')->group(function () {
    Route::post('login', [TokenController::class, 'store'])->middleware('throttle:auth');
    Route::post('me/presence/disconnect', [TokenController::class, 'disconnect']);

    Route::get('reset-password/validate', [PasswordResetController::class, 'validateToken'])
        ->middleware('throttle:auth');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('logout', [TokenController::class, 'destroy']);
        Route::get('me', [TokenController::class, 'me']);
        Route::patch('me/status', [TokenController::class, 'updateStatus']);
        Route::post('me/presence/ping', [TokenController::class, 'ping']);

        Route::post('me/avatar', [AvatarController::class, 'store'])->middleware('throttle:10,1');
        Route::delete('me/avatar', [AvatarController::class, 'destroy']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('device-tokens', [DeviceTokenController::class, 'destroy']);
    Route::delete('device-tokens/{deviceTokenId}', [DeviceTokenController::class, 'destroyById']);
});

Route::post('webhooks/livekit', \Modules\Workspace\Http\Controllers\LiveKitWebhookController::class);

Route::middleware(['auth:sanctum', 'staff'])->group(function (): void {
    Route::prefix('calls')->group(function (): void {
        Route::post('/', [\Modules\Workspace\Http\Controllers\CallController::class, 'create'])
            ->middleware('throttle:10,1');
        Route::get('active', [\Modules\Workspace\Http\Controllers\CallController::class, 'active']);
        Route::get('history', [\Modules\Workspace\Http\Controllers\CallController::class, 'history']);
        Route::get('{callId}', [\Modules\Workspace\Http\Controllers\CallController::class, 'show']);
        Route::post('{callId}/accept', [\Modules\Workspace\Http\Controllers\CallController::class, 'accept']);
        Route::post('{callId}/decline', [\Modules\Workspace\Http\Controllers\CallController::class, 'decline']);
        Route::post('{callId}/cancel', [\Modules\Workspace\Http\Controllers\CallController::class, 'cancel']);
        Route::post('{callId}/leave', [\Modules\Workspace\Http\Controllers\CallController::class, 'leave']);
        Route::post('{callId}/invite', [\Modules\Workspace\Http\Controllers\CallController::class, 'invite']);
        Route::post('{callId}/end', [\Modules\Workspace\Http\Controllers\CallController::class, 'end']);
    });
});

Route::middleware(['auth:sanctum', 'staff', 'admin'])
    ->prefix('admin')
    ->group(function (): void {
        Route::apiResource('users', UserController::class);
        Route::post('users/{userId}/resend-invite', [UserController::class, 'resendInvite']);
        Route::get('audit-logs', [AuditLogController::class, 'index']);
    });

Route::middleware(['auth:sanctum', 'staff'])
    ->group(function (): void {
        Route::get('users', [UserController::class, 'index']);
        Route::get('search', SearchController::class);
    });

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/auth/phone', [ProfileController::class, 'updatePhone']);
});
