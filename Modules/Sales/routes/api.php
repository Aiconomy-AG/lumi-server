<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\CartController;
use Modules\Sales\Http\Controllers\WishlistController;
use Modules\Sales\Http\Controllers\CheckoutController;

Route::middleware(['auth:sanctum'])->prefix('v1/shop')->group(function (): void {
        Route::get(
            '/customers/{customerId}/cart',
            [CartController::class, 'show']
        );

        Route::post(
            '/customers/{customerId}/cart/items',
            [CartController::class, 'storeItem']
        );

        Route::put(
            '/customers/{customerId}/cart/items/{productId}',
            [CartController::class, 'updateItem']
        );

        Route::delete(
            '/customers/{customerId}/cart/items/{productId}',
            [CartController::class, 'destroyItem']
        );

        Route::get(
            '/customers/{customerId}/wishlist',
            [WishlistController::class, 'index']
        );

        Route::post(
            '/customers/{customerId}/wishlist',
            [WishlistController::class, 'store']
        );

        Route::delete(
            '/customers/{customerId}/wishlist/{productId}',
            [WishlistController::class, 'destroy']
        );
    Route::prefix('shop')->group(function () {
        Route::post('orders', [CheckoutController::class, 'store']);
        Route::get('customers/{customerId}/orders', [CheckoutController::class, 'customerOrders']);
        Route::get('orders/{orderId}', [CheckoutController::class, 'show']);
    });
});
