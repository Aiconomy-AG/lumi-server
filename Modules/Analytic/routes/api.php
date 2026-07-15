<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytic\Http\Controllers\AnalyticController;
use Modules\Analytic\Http\Controllers\ProductAnalyticsController;

Route::middleware('auth:sanctum')
    ->prefix('analytics')
    ->group(function (): void {
        Route::get(
            '/products/most-wishlisted',
            [ProductAnalyticsController::class, 'mostWishlisted']
        );
    });
