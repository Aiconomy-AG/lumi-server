<?php

use Illuminate\Support\Facades\Route;
use Modules\Workspace\Http\Controllers\AiActionController;
use Modules\Workspace\Http\Controllers\CallController;
use Modules\Workspace\Http\Controllers\ConversationController;
use Modules\Workspace\Http\Controllers\MessageController;
use Modules\Workspace\Http\Controllers\NotificationController;
use Modules\Workspace\Http\Controllers\ProjectController;
use Modules\Workspace\Http\Controllers\ReturnRequestController;
use Modules\Workspace\Http\Controllers\TaskController;
use Modules\Workspace\Http\Controllers\TimeTrackingController;
use Modules\Workspace\Http\Controllers\WorkspaceController;
use Modules\Workspace\Http\Middleware\VerifyConversationParticipant;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('workspaces', WorkspaceController::class)->names('workspace');

    Route::prefix('workspace')->group(function (): void {
        Route::middleware('staff')->group(function (): void {
            Route::get('projects', [ProjectController::class, 'index']);
            Route::post('projects', [ProjectController::class, 'store']);
            Route::get('projects/{projectId}', [ProjectController::class, 'show']);
            Route::put('projects/{projectId}', [ProjectController::class, 'update']);
            Route::delete('projects/{projectId}', [ProjectController::class, 'destroy']);

            Route::get('returns', [ReturnRequestController::class, 'index']);
            Route::get('returns/{returnRequestId}', [ReturnRequestController::class, 'show']);
            Route::patch('returns/{returnRequestId}', [ReturnRequestController::class, 'update']);

            Route::get('tasks', [TaskController::class, 'index']);
            Route::post('tasks', [TaskController::class, 'store']);
            Route::get('tasks/{taskId}', [TaskController::class, 'show']);
            Route::put('tasks/{taskId}', [TaskController::class, 'update']);
            Route::delete('tasks/{taskId}', [TaskController::class, 'destroy']);

            Route::prefix('tasks/{taskId}')->group(function (): void {
                Route::post('assignees', [TaskController::class, 'assignEmployees']);
                Route::delete('assignees/{employeeId}', [TaskController::class, 'removeEmployee']);

                Route::controller(TimeTrackingController::class)->prefix('time-entries')->group(function () {
                    Route::get('/', 'index');
                    Route::post('start', 'start');
                    Route::post('{entryId}/stop', 'stop');
                });
            });
        });

        Route::get('conversations', [ConversationController::class, 'index']);
        Route::post('conversations', [ConversationController::class, 'store']);
        Route::get('conversations/{conversationId}', [ConversationController::class, 'show']);
        Route::put('conversations/{conversationId}', [ConversationController::class, 'update'])
            ->middleware(VerifyConversationParticipant::class);
        Route::post('conversations/{conversationId}/leave', [ConversationController::class, 'leave'])
            ->middleware(VerifyConversationParticipant::class);
        Route::delete('conversations/{conversationId}', [ConversationController::class, 'destroy']);

        Route::middleware([VerifyConversationParticipant::class])
            ->prefix('conversations/{conversationId}')
            ->group(function (): void {
                Route::get('messages', [MessageController::class, 'index']);
                Route::post('messages', [MessageController::class, 'store']);
                Route::post('messages/{messageId}/reactions', [MessageController::class, 'react']);
                Route::delete('messages/{messageId}/reactions', [MessageController::class, 'unreact']);
                Route::post('calls', [CallController::class, 'store'])->middleware(['staff', 'throttle:10,1']);

                Route::post('ai-actions/{actionId}/approve', [AiActionController::class, 'approve'])
                    ->middleware('throttle:15,1');
                Route::post('ai-actions/{actionId}/reject', [AiActionController::class, 'reject'])
                    ->middleware('throttle:15,1');
            });

        Route::middleware('staff')->group(function (): void {
            Route::get('calls/active', [CallController::class, 'active']);
            Route::get('calls/history', [CallController::class, 'history']);
            Route::get('calls/{callId}', [CallController::class, 'show']);
            Route::post('calls/{callId}/accept', [CallController::class, 'accept']);
            Route::post('calls/{callId}/decline', [CallController::class, 'decline']);
            Route::post('calls/{callId}/cancel', [CallController::class, 'cancel']);
            Route::post('calls/{callId}/leave', [CallController::class, 'leave']);
            Route::post('calls/{callId}/invite', [CallController::class, 'invite']);
            Route::post('calls/{callId}/end', [CallController::class, 'end']);
        });

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::put('notifications/{notificationId}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('notifications/{notificationId}', [NotificationController::class, 'dismiss']);

        Route::get('users/{userId}/time-entries/daily-total', [TimeTrackingController::class, 'dailyTotal']);
        Route::get('me/active-time-entry', [TimeTrackingController::class, 'active']);
    });
});
