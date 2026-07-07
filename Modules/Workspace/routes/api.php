<?php

use Illuminate\Support\Facades\Route;
use Modules\Workspace\Http\Controllers\WorkspaceController;
use App\Http\Controllers\Workspace\EmployeeController;


Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::prefix('workspace')->group(function (): void {

        Route::get('employees', [EmployeeController::class, 'index']);
        Route::post('employees', [EmployeeController::class, 'store']);

        Route::prefix('employees/{employeeId}')->group(function (): void {
            Route::get('/', [EmployeeController::class, 'show']);
            Route::put('/', [EmployeeController::class, 'update']);
            Route::delete('/', [EmployeeController::class, 'destroy']);
        });
    });
    Route::apiResource('workspaces', WorkspaceController::class)->names('workspace');
});
