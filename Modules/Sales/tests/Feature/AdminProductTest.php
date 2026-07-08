<?php

namespace Modules\Sales\Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Modules\Sales\Jobs\DeleteShopifyProductJob;
use Modules\Sales\Jobs\SyncShopifyProductJob;
use Modules\Sales\Models\Category;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_update_and_patch_stock_but_cannot_delete(): void
    {
        Queue::fake();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employee]));

        $createResponse = $this->postJson('/api/v1/admin/products', [
            'name' => 'Panel Product',
            'price' => 10.5,
        ])->assertCreated();

        $productId = $createResponse->json('data.id');

        Queue::assertPushed(SyncShopifyProductJob::class);

        $this->putJson("/api/v1/admin/products/{$productId}", [
            'name' => 'Updated Product',
            'price' => 11,
        ])->assertOk();

        Queue::assertPushed(SyncShopifyProductJob::class, 2);

        $variant = ProductVariant::create([
            'product_id' => $productId,
            'sku' => 'sku-1',
            'price' => 11,
            'weight' => 1,
            'weight_unit' => 'kg',
            'stock_quantity' => 2,
        ]);

        $this->patchJson("/api/v1/admin/products/{$productId}/variants/{$variant->id}", [
            'stock_quantity' => 9,
        ])->assertOk()->assertJsonPath('data.variants.0.stock_quantity', 9);

        Queue::assertPushed(SyncShopifyProductJob::class, 3);

        $this->deleteJson("/api/v1/admin/products/{$productId}")
            ->assertForbidden();
    }

    public function test_admin_can_delete_product(): void
    {
        Queue::fake();

        Sanctum::actingAs(User::factory()->admin()->create());

        $product = Product::create([
            'name' => 'Delete Me',
            'price' => 15,
            'shopify_product_id' => 'gid://shopify/Product/999',
        ]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}")
            ->assertNoContent();

        Queue::assertPushed(DeleteShopifyProductJob::class);
    }

    public function test_index_is_paginated_with_meta(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        Product::factory()->count(3)->create();

        $this->getJson('/api/v1/admin/products?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_index_filters_by_category(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $bath = Category::create(['name' => 'Baden']);
        $hair = Category::create(['name' => 'Haare']);

        Product::factory()->create(['name' => 'Bath Bomb', 'category_id' => $bath->id]);
        Product::factory()->create(['name' => 'Shampoo Bar', 'category_id' => $hair->id]);

        $this->getJson("/api/v1/admin/products?category_id={$bath->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bath Bomb');
    }

    public function test_index_search_matches_product_and_variant_fields(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $match = Product::factory()->create(['name' => 'Sleepy Lotion']);
        ProductVariant::factory()->create(['product_id' => $match->id, 'sku' => 'SLP-100']);

        $other = Product::factory()->create(['name' => 'Shower Gel']);
        ProductVariant::factory()->create(['product_id' => $other->id, 'sku' => 'SHW-100']);

        $this->getJson('/api/v1/admin/products?search=Sleepy')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sleepy Lotion');

        $this->getJson('/api/v1/admin/products?search=SLP-100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sleepy Lotion');
    }

    public function test_index_filters_by_stock_status(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $inStock = Product::factory()->create(['name' => 'Plenty']);
        ProductVariant::factory()->create(['product_id' => $inStock->id, 'stock_quantity' => 50]);

        $lowStock = Product::factory()->create(['name' => 'Scarce']);
        ProductVariant::factory()->create(['product_id' => $lowStock->id, 'stock_quantity' => 3]);

        $outOfStock = Product::factory()->create(['name' => 'Gone']);
        ProductVariant::factory()->create(['product_id' => $outOfStock->id, 'stock_quantity' => 0]);

        $this->getJson('/api/v1/admin/products?stock_status=low_stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Scarce');

        $this->getJson('/api/v1/admin/products?stock_status=out_of_stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Gone');
    }

    public function test_index_rejects_invalid_filters(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/admin/products?stock_status=bogus')
            ->assertUnprocessable();

        $this->getJson('/api/v1/admin/products?per_page=1000')
            ->assertUnprocessable();
    }

    public function test_store_accepts_variant_name_and_options(): void
    {
        Queue::fake();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/admin/products', [
            'name' => 'Shower Gel',
            'price' => 12.5,
            'variants' => [[
                'sku' => 'SG-120',
                'name' => 'Shower Gel, 120 g',
                'stock_quantity' => 7,
                'options' => ['Grösse' => '120 g'],
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.variants.0.name', 'Shower Gel, 120 g')
            ->assertJsonPath('data.variants.0.options.Grösse', '120 g');

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'SG-120',
            'name' => 'Shower Gel, 120 g',
        ]);
    }
}
