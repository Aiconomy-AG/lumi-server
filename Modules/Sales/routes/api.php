<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\Admin\ProductController;
use Modules\Sales\Http\Controllers\Admin\ProductVariantController;
use Modules\Sales\Http\Controllers\CatalogController;
use Modules\Sales\Http\Controllers\Shopify\ProxyReturnController;
use Modules\Sales\Http\Controllers\Shopify\ProxyWishlistController;
use Modules\Sales\Http\Controllers\Shopify\WebhookController;
use Modules\Sales\Models\Product;

// -------------------------------------------------------------------------
// Shopify Integrations
// -------------------------------------------------------------------------
Route::prefix('shopify/proxy')->group(function (): void {
    Route::controller(ProxyWishlistController::class)->prefix('wishlist')->group(function () {
        Route::get('/', 'index');
        Route::post('items', 'store');
        Route::delete('items/{shopifyProductId}', 'destroy')->where('shopifyProductId', '.*');
    });

    Route::post('returns', [ProxyReturnController::class, 'store']);
});

Route::prefix('shopify/webhooks')->controller(WebhookController::class)->group(function (): void {
    Route::post('customers/create', 'customer');
    Route::post('customers/update', 'customer');
    Route::post('orders/create', 'order');
    Route::post('orders/updated', 'order');
    Route::post('products/update', 'product');
});

// -------------------------------------------------------------------------
// Shop Products (public read, policy-gated writes)
// -------------------------------------------------------------------------
Route::prefix('shop')->controller(CatalogController::class)->group(function (): void {
    // Public reads
    Route::get('products', 'index');
    Route::get('products/{productId}', 'show');
    Route::get('products/{productId}/variants', 'productVariants');
    Route::get('products/{productId}/ingredients', 'productIngredients');

    Route::get('categories', 'categories');
    Route::get('ingredients', 'ingredients');
    Route::get('ingredients/{ingredientId}', 'ingredientDetails');
    Route::get('variants/{variantId}', 'variantDetails');
});

Route::middleware(['auth:sanctum'])->prefix('shop')->group(function (): void {
    // Writes — same path, policy decides who can do it
    Route::controller(ProductController::class)->prefix('products')->group(function () {
        Route::post('/', 'store')->middleware('can:create,' . Product::class);
        Route::put('{productId}', 'update');
        Route::delete('{productId}', 'destroy');
    });

    Route::controller(ProductVariantController::class)->prefix('products/{productId}/variants')->group(function () {
        Route::post('/', 'store');
        Route::put('{variantId}', 'update');
        Route::patch('{variantId}', 'updateStock');
        Route::delete('{variantId}', 'destroy');
    });
});
