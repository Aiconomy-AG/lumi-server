<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\SalesController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('sales', SalesController::class)->names('sales');
});

Route::get('/api-docs-sales', function () {
    return view('sales::swagger', [
        'url' => '/docs/api/v1/Shop.yaml',
        'title' => 'Shop API Documentation'
    ]);
});

Route::get('/api-docs-workspace', function () {
    return view('sales::swagger', [
        'url' => '/docs/api/v1/Workspace.yaml',
        'title' => 'Workspace API Documentation'
    ]);
});
