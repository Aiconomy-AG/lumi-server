<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytic\Http\Controllers\AnalyticController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('analytics', AnalyticController::class)->names('analytic');
});
