<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\CartController;
use Modules\Sales\Http\Controllers\CheckoutController;
use Modules\Sales\Http\Controllers\WishlistController;

Route::middleware(['auth:sanctum'])
    ->prefix('v1/shop')
    ->group(function (): void {
        Route::prefix('customers/{customerId}')->group(function (): void {
            Route::get(
                'cart',
                [CartController::class, 'show']
            );

            Route::post(
                'cart/items',
                [CartController::class, 'storeItem']
            );

            Route::put(
                'cart/items/{productId}',
                [CartController::class, 'updateItem']
            );

            Route::delete(
                'cart/items/{productId}',
                [CartController::class, 'destroyItem']
            );

            Route::get(
                'wishlist',
                [WishlistController::class, 'index']
            );

            Route::post(
                'wishlist',
                [WishlistController::class, 'store']
            );

            Route::delete(
                'wishlist/{productId}',
                [WishlistController::class, 'destroy']
            );

            Route::get(
                'orders',
                [CheckoutController::class, 'customerOrders']
            );
        });

        Route::post(
            'orders',
            [CheckoutController::class, 'store']
        );

        Route::get(
            'orders/{orderId}',
            [CheckoutController::class, 'show']
        );
    });
