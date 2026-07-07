<?php

use Modules\Workspace\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;
use Modules\Workspace\Http\Controllers\WorkspaceController;
use Modules\Workspace\Http\Controllers\ProjectController;
use Modules\Workspace\Http\Controllers\TaskController;


Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::prefix('workspace')->group(function (): void {

        Route::get('employees', [EmployeeController::class, 'index']);
        Route::post('employees', [EmployeeController::class, 'store']);

        Route::prefix('employees/{employeeId}')->group(function (): void {
            Route::get('/', [EmployeeController::class, 'show']);
            Route::put('/', [EmployeeController::class, 'update']);
            Route::delete('/', [EmployeeController::class, 'destroy']);
        });

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

        Route::post(
            'tasks/{taskId}/assignees',
            [TaskController::class, 'assignEmployees']
        );

        Route::delete(
            'tasks/{taskId}/assignees/{employeeId}',
            [TaskController::class, 'removeEmployee']
        );
    });
    Route::apiResource('workspaces', WorkspaceController::class)->names('workspace');
});
