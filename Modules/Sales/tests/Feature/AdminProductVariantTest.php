<?php

namespace Modules\Sales\Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Modules\Sales\Jobs\SyncShopifyProductJob;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Tests\TestCase;

class AdminProductVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_and_update_variant(): void
    {
        Queue::fake();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employee]));

        $product = Product::factory()->create(['price' => 20]);

        $this->postJson("/api/v1/admin/products/{$product->id}/variants", [
            'sku' => 'VAR-NEW-1',
            'name' => 'Red, 100 g',
            'weight' => 100,
            'weight_unit' => 'g',
            'colour' => 'Red',
            'stock_quantity' => 4,
            'options' => ['Farbe' => 'Red', 'Grösse' => '100 g'],
        ])->assertCreated()
            ->assertJsonPath('data.variants.0.sku', 'VAR-NEW-1')
            ->assertJsonPath('data.variants.0.options.Farbe', 'Red')
            // pretul cade pe pretul produsului cand lipseste
            ->assertJsonPath('data.variants.0.price', '20.00');

        $variantId = ProductVariant::where('sku', 'VAR-NEW-1')->value('id');

        $this->putJson("/api/v1/admin/products/{$product->id}/variants/{$variantId}", [
            'price' => 25.5,
            'colour' => 'Blue',
        ])->assertOk()
            ->assertJsonPath('data.variants.0.colour', 'Blue');

        Queue::assertPushed(SyncShopifyProductJob::class, 2);
    }

    public function test_update_rejects_duplicate_sku_but_allows_own(): void
    {
        Queue::fake();

        Sanctum::actingAs(User::factory()->admin()->create());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'KEEP-1']);
        ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'TAKEN-1']);

        $this->putJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}", [
            'sku' => 'TAKEN-1',
        ])->assertUnprocessable();

        $this->putJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}", [
            'sku' => 'KEEP-1',
            'name' => 'renamed',
        ])->assertOk();
    }

    public function test_variant_must_belong_to_product(): void
    {
        Queue::fake();

        Sanctum::actingAs(User::factory()->admin()->create());

        $product = Product::factory()->create();
        $otherVariant = ProductVariant::factory()->create();

        $this->putJson("/api/v1/admin/products/{$product->id}/variants/{$otherVariant->id}", [
            'name' => 'hijack',
        ])->assertNotFound();

        $this->deleteJson("/api/v1/admin/products/{$product->id}/variants/{$otherVariant->id}")
            ->assertNotFound();
    }

    public function test_admin_can_delete_variant_but_employee_cannot(): void
    {
        Queue::fake();

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employee]));
        $this->deleteJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}")
            ->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->deleteJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);

        Queue::assertPushed(SyncShopifyProductJob::class);
    }
}
