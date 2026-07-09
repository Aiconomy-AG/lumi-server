<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\Admin\OrderController;
use Modules\Sales\Http\Controllers\Admin\ProductController;
use Modules\Sales\Http\Controllers\Admin\ProductVariantController;
use Modules\Sales\Http\Controllers\Admin\ReturnRequestController;
use Modules\Sales\Http\Controllers\CartController;
use Modules\Sales\Http\Controllers\CatalogController;
use Modules\Sales\Http\Controllers\CheckoutController;
use Modules\Sales\Http\Controllers\CustomerController;
use Modules\Sales\Http\Controllers\Shopify\ProxyReturnController;
use Modules\Sales\Http\Controllers\Shopify\ProxyWishlistController;
use Modules\Sales\Http\Controllers\Shopify\WebhookController;
use Modules\Sales\Http\Controllers\WishlistController;
use Modules\Sales\Http\Middleware\VerifyCustomerOwnership;

// -------------------------------------------------------------------------
// Shopify Integrations
// -------------------------------------------------------------------------
Route::prefix('shopify/proxy')->group(function (): void {
    Route::controller(ProxyWishlistController::class)->prefix('wishlist')->group(function () {
        Route::get('/', 'index');
        Route::post('items', 'store');
        Route::delete('items/{shopifyProductId}', 'destroy')->where('shopifyProductId', '.*');
    });

    Route::controller(ProxyReturnController::class)
        ->prefix('returns')
        ->group(function (): void {
            Route::post('/', 'store');
        });
});

Route::prefix('shopify/webhooks')->controller(WebhookController::class)->group(function (): void {
    Route::post('customers/create', 'customer');
    Route::post('customers/update', 'customer');
    Route::post('orders/create', 'order');
    Route::post('orders/updated', 'order');
    Route::post('products/update', 'product');
});

// -------------------------------------------------------------------------
// Shop Catalog (public reads)
// -------------------------------------------------------------------------
Route::prefix('shop')->controller(CatalogController::class)->group(function (): void {
    Route::get('products', 'index');
    Route::get('products/{productId}', 'show');
    Route::get('products/{productId}/variants', 'productVariants');
    Route::get('products/{productId}/ingredients', 'productIngredients');

    Route::get('categories', 'categories');
    Route::get('ingredients', 'ingredients');
    Route::get('ingredients/{ingredientId}', 'ingredientDetails');
    Route::get('variants/{variantId}', 'variantDetails');
});

// -------------------------------------------------------------------------
// Shop Customer Area (authenticated)
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum'])->prefix('shop')->group(function (): void {
    Route::get('me', [CustomerController::class, 'me']);

    Route::prefix('customers/{customerId}')
        ->middleware(VerifyCustomerOwnership::class)
        ->group(function (): void {
            Route::get('/', [CustomerController::class, 'show']);

            Route::controller(CartController::class)->prefix('cart')->group(function () {
                Route::get('/', 'show');
                Route::post('items', 'storeItem');
                Route::put('items/{productVariantId}', 'updateItem');
                Route::delete('items/{productVariantId}', 'destroyItem');
            });

            Route::controller(WishlistController::class)->prefix('wishlist')->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::delete('{productId}', 'destroy');
            });

            Route::get('orders', [CheckoutController::class, 'customerOrders']);
        });

    Route::post('orders', [CheckoutController::class, 'store']);
    Route::get('orders/{orderId}', [CheckoutController::class, 'show']);
});

// -------------------------------------------------------------------------
// Admin (staff-only; ProductPolicy decides write access)
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'staff'])->prefix('admin')->group(function (): void {
    Route::controller(ProductController::class)->prefix('products')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('{productId}', 'update');
        Route::delete('{productId}', 'destroy');
    });

    Route::controller(ProductVariantController::class)->prefix('products/{productId}/variants')->group(function () {
        Route::post('/', 'store');
        Route::put('{variantId}', 'update');
        Route::patch('{variantId}', 'updateStock');
        Route::delete('{variantId}', 'destroy');
    });

    Route::get('orders', [OrderController::class, 'index']);

    Route::controller(ReturnRequestController::class)
        ->prefix('returns')
        ->group(function (): void {
            Route::get('/', 'index');
            Route::get('{returnRequestId}', 'show');
            Route::patch('{returnRequestId}', 'update');
        });
});
