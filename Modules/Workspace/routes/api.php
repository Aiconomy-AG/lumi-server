<?php

use Illuminate\Support\Facades\Route;
use Modules\Workspace\Http\Controllers\ConversationController;
use Modules\Workspace\Http\Controllers\TimeTrackingController;
use Modules\Workspace\Http\Controllers\ProjectController;
use Modules\Workspace\Http\Controllers\TaskController;
use Modules\Workspace\Http\Controllers\WorkspaceController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('workspaces', WorkspaceController::class)->names('workspace');
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


            Route::post(
                'tasks/{taskId}/assignees',
                [TaskController::class, 'assignEmployees']
            );

            Route::delete(
                'tasks/{taskId}/assignees/{employeeId}',
                [TaskController::class, 'removeEmployee']
            );

            Route::prefix('tasks/{taskId}')->group(function (): void {

                // Assignees
                Route::post('assignees', [TaskController::class, 'assignEmployees']);
                Route::delete('assignees/{employeeId}', [TaskController::class, 'removeEmployee']);

                // Time Entries
                Route::controller(TimeTrackingController::class)->prefix('time-entries')->group(function () {
                    Route::get('/', 'index');
                    Route::post('start', 'start');
                    Route::post('{entryId}/stop', 'stop');
                });
            });
        });
    });
});
