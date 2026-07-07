<?php

use Modules\Workspace\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;
use Modules\Workspace\Http\Controllers\WorkspaceController;
use Modules\Workspace\Http\Controllers\ProjectController;


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
    });
    Route::apiResource('workspaces', WorkspaceController::class)->names('workspace');
});
