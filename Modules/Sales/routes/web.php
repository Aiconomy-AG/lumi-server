<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    //
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
