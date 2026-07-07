<?php

use Illuminate\Support\Facades\Route;
use Modules\Workspace\Http\Controllers\WorkspaceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('workspaces', WorkspaceController::class)->names('workspace');
});
