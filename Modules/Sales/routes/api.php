<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\SalesController;
use Modules\Sales\Http\Controllers\CheckoutController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('sales', SalesController::class)->names('sales');

    Route::prefix('shop')->group(function () {
        Route::post('orders', [CheckoutController::class, 'store']);
        Route::get('customers/{customerId}/orders', [CheckoutController::class, 'customerOrders']);
        Route::get('orders/{orderId}', [CheckoutController::class, 'show']);
    });
});
