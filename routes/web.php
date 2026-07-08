<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api-docs-sales', function () {
    return view('swagger', [
        'url' => '/docs/api/v1/Shop.yaml',
        'title' => 'Shop API Documentation'
    ]);
});

Route::get('/api-docs-workspace', function () {
    return view('swagger', [
        'url' => '/docs/api/v1/Workspace.yaml',
        'title' => 'Workspace API Documentation'
    ]);
});
