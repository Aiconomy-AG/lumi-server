<?php

namespace Modules\Sales\Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_update_and_patch_stock_but_cannot_delete(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employee]));

        $createResponse = $this->postJson('/api/v1/admin/products', [
            'name' => 'Panel Product',
            'price' => 10.5,
        ])->assertCreated();

        $productId = $createResponse->json('data.id');

        $this->putJson("/api/v1/admin/products/{$productId}", [
            'name' => 'Updated Product',
            'price' => 11,
        ])->assertOk();

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

        $this->deleteJson("/api/v1/admin/products/{$productId}")
            ->assertForbidden();
    }

    public function test_admin_can_delete_product(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $product = Product::create([
            'name' => 'Delete Me',
            'price' => 15,
        ]);

        $this->deleteJson("/api/v1/admin/products/{$product->id}")
            ->assertNoContent();
    }
}
