<?php

namespace Modules\Sales\Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Modules\Sales\Integrations\Shopify\ProductSyncService;
use Modules\Sales\Models\Category;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent actual Shopify API calls during tests
        $this->mock(ProductSyncService::class, function (MockInterface $mock) {
            $mock->shouldReceive('create')->andReturnNull();
            $mock->shouldReceive('update')->andReturnNull();
            $mock->shouldReceive('queueDelete')->andReturnNull();
            $mock->shouldReceive('createVariant')->andReturnNull();
            $mock->shouldReceive('updateVariant')->andReturnNull();
            $mock->shouldReceive('deleteVariant')->andReturnNull();
        });
    }

    private function makeUser(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function makeCategory(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'name' => 'Test Category',
        ], $overrides));
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Test Product',
            'sku' => 'SKU-' . uniqid(),
            'price' => 10.00,
        ], $overrides));
    }

    private function makeVariant(Product $product, array $overrides = []): ProductVariant
    {
        return ProductVariant::create(array_merge([
            'product_id' => $product->id,
            'sku' => 'VAR-' . uniqid(),
            'price' => $product->price,
            'stock_quantity' => 0,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // View Tests (Admins & Employees)
    // -------------------------------------------------------------------------

    public function test_index_returns_products_for_admin(): void
    {
        $user = $this->makeUser(UserRole::Admin);
        Sanctum::actingAs($user);

        $this->makeProduct();

        $this->getJson('/api/v1/admin/products')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_returns_products_for_employee(): void
    {
        $user = $this->makeUser(UserRole::Employee);
        Sanctum::actingAs($user);

        $this->makeProduct();

        $this->getJson('/api/v1/admin/products')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/products')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Create / Update / Delete Product (Admins & Employees)
    // -------------------------------------------------------------------------

    public function test_store_creates_a_product_when_user_is_admin(): void
    {
        $user = $this->makeUser(UserRole::Admin);
        Sanctum::actingAs($user);

        $category = $this->makeCategory();

        $response = $this->postJson('/api/v1/admin/products', [
            'name' => 'New Admin Product',
            'price' => 99.99,
            'sku' => 'ADMIN-123',
            'category_id' => $category->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['sku' => 'ADMIN-123']);
    }

    public function test_store_creates_a_product_when_user_is_employee(): void
    {
        $user = $this->makeUser(UserRole::Employee);
        Sanctum::actingAs($user);

        $category = $this->makeCategory();

        $response = $this->postJson('/api/v1/admin/products', [
            'name' => 'New Employee Product',
            'price' => 49.99,
            'sku' => 'EMP-123',
            'category_id' => $category->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['sku' => 'EMP-123']);
    }

    public function test_destroy_deletes_product_when_user_is_admin(): void
    {
        $user = $this->makeUser(UserRole::Admin);
        Sanctum::actingAs($user);

        $product = $this->makeProduct();

        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertNoContent();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_destroy_returns_403_when_user_is_employee(): void
    {
        $user = $this->makeUser(UserRole::Employee);
        Sanctum::actingAs($user);

        $product = $this->makeProduct();

        $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertStatus(403);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    // -------------------------------------------------------------------------
    // Variant Stock & Details (Granular Permissions)
    // -------------------------------------------------------------------------

    public function test_update_variant_details_succeeds_when_user_is_employee(): void
    {
        $user = $this->makeUser(UserRole::Employee);
        Sanctum::actingAs($user);

        $product = $this->makeProduct();
        $variant = $this->makeVariant($product, ['price' => 10.00]);

        $response = $this->putJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}", [
            'price' => 50.00,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'price' => 50.00,
        ]);
    }

    public function test_update_stock_succeeds_when_user_is_employee(): void
    {
        $user = $this->makeUser(UserRole::Employee);
        Sanctum::actingAs($user);

        $product = $this->makeProduct();
        $variant = $this->makeVariant($product, ['stock_quantity' => 5]);

        $response = $this->patchJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}", [
            'stock_quantity' => 25,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock_quantity' => 25, // Updated successfully
        ]);
    }

    public function test_update_stock_succeeds_when_user_is_admin(): void
    {
        $user = $this->makeUser(UserRole::Admin);
        Sanctum::actingAs($user);

        $product = $this->makeProduct();
        $variant = $this->makeVariant($product, ['stock_quantity' => 5]);

        $response = $this->patchJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}", [
            'stock_quantity' => 100,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock_quantity' => 100,
        ]);
    }

    public function test_update_stock_validates_required_fields(): void
    {
        $user = $this->makeUser(UserRole::Employee);
        Sanctum::actingAs($user);

        $product = $this->makeProduct();
        $variant = $this->makeVariant($product);

        $this->patchJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stock_quantity']);
    }
}
