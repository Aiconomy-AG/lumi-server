<?php

use Illuminate\Support\Facades\Route;
use Modules\Workspace\Http\Controllers\ConversationController;
use Modules\Workspace\Http\Controllers\MessageController;
use Modules\Workspace\Http\Controllers\NotificationController;
use Modules\Workspace\Http\Controllers\ProjectController;
use Modules\Workspace\Http\Controllers\TaskController;
use Modules\Workspace\Http\Controllers\TimeTrackingController;
use Modules\Workspace\Http\Middleware\VerifyConversationParticipant;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::prefix('workspace')->group(function (): void {
        Route::get('projects', [ProjectController::class, 'index']);
        Route::post('projects', [ProjectController::class, 'store']);
        Route::get('projects/{projectId}', [ProjectController::class, 'show']);
        Route::put('projects/{projectId}', [ProjectController::class, 'update']);
        Route::delete('projects/{projectId}', [ProjectController::class, 'destroy']);

        Route::get('tasks', [TaskController::class, 'index']);
        Route::post('tasks', [TaskController::class, 'store']);
        Route::get('tasks/{taskId}', [TaskController::class, 'show']);
        Route::put('tasks/{taskId}', [TaskController::class, 'update']);
        Route::delete('tasks/{taskId}', [TaskController::class, 'destroy']);

        Route::get('conversations', [ConversationController::class, 'index']);
        Route::post('conversations', [ConversationController::class, 'store']);
        Route::get('conversations/{conversationId}', [ConversationController::class, 'show']);

        Route::middleware([VerifyConversationParticipant::class])
            ->prefix('conversations/{conversationId}')
            ->group(function (): void {
                Route::get('messages', [MessageController::class, 'index']);
                Route::post('messages', [MessageController::class, 'store']);
            });

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::put('notifications/{notificationId}/read', [NotificationController::class, 'markAsRead']);

        Route::post(
            'tasks/{taskId}/assignees',
            [TaskController::class, 'assignEmployees']
        );

        Route::delete(
            'tasks/{taskId}/assignees/{employeeId}',
            [TaskController::class, 'removeEmployee']
        );

        Route::prefix('tasks/{taskId}')->group(function (): void {
            Route::post('time-entries/start', [TimeTrackingController::class, 'start']);
            Route::post('time-entries/{entryId}/stop', [TimeTrackingController::class, 'stop']);
            Route::get('time-entries', [TimeTrackingController::class, 'index']);
        });
    });
});
