<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\Admin\OrderController;
use Modules\Sales\Http\Controllers\Admin\ProductController;
use Modules\Sales\Http\Controllers\Admin\ProductVariantController;
use Modules\Sales\Http\Controllers\CartController;
use Modules\Sales\Http\Controllers\CatalogController;
use Modules\Sales\Http\Controllers\CheckoutController;
use Modules\Sales\Http\Controllers\CustomerController;
use Modules\Sales\Http\Controllers\WishlistController;
use Modules\Sales\Http\Middleware\VerifyCustomerOwnership;

Route::prefix('v1/shop')->group(function (): void {
    Route::get('products', [CatalogController::class, 'index']);
    Route::get('products/{productId}', [CatalogController::class, 'show']);
    Route::get('categories', [CatalogController::class, 'categories']);

    Route::get('products/{productId}/variants', [CatalogController::class, 'productVariants']);
    Route::get('variants/{variantId}', [CatalogController::class, 'variantDetails']);

    Route::get('ingredients', [CatalogController::class, 'ingredients']);
    Route::get('ingredients/{ingredientId}', [CatalogController::class, 'ingredientDetails']);
    Route::get('products/{productId}/ingredients', [CatalogController::class, 'productIngredients']);
});

Route::middleware(['auth:sanctum'])
    ->prefix('v1/shop')
    ->group(function (): void {
        Route::get('me', [CustomerController::class, 'me']);

        Route::prefix('customers/{customerId}')
            ->middleware(VerifyCustomerOwnership::class)
            ->group(function (): void {

                Route::get('/', [CustomerController::class, 'show']);

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

Route::middleware(['auth:sanctum', 'staff'])
    ->prefix('v1/admin')
    ->group(function (): void {
        Route::get('products', [ProductController::class, 'index']);
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{productId}', [ProductController::class, 'update']);
        Route::delete('products/{productId}', [ProductController::class, 'destroy'])->middleware('admin');
        Route::patch('products/{productId}/variants/{variantId}', [ProductVariantController::class, 'updateStock']);
        Route::get('orders', [OrderController::class, 'index']);
    });
