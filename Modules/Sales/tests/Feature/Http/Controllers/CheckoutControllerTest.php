<?php

namespace Modules\Sales\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\Order;
use Modules\Sales\Models\OrderItem;
use Modules\Sales\Models\Product;
use Tests\TestCase;

class CheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomerForUser(User $user, array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'username' => $user->name,
            'email' => $user->email,
            'shopify_customer_id' => 'mock_cus_' . uniqid(),
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

    private function makeOrder(Customer $customer, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'subtotal' => 10.00,
            'shipping_cost' => 5.00,
            'total_amount' => 15.00,
            'shipping_address' => '123 Main St',
            'payment_method' => 'card',
            'payment_status' => 'unshipped',
        ], $overrides));
    }

    public function test_store_creates_an_order_for_the_resolved_customer(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = $this->makeProduct(['price' => 20.00]);

        $response = $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => '456 Oak Ave',
            'payment_method' => 'card',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('customers', ['email' => $user->email]);

        $customer = Customer::where('email', $user->email)->first();

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'subtotal' => 40.00,
            'shipping_cost' => 5.00,
            'total_amount' => 45.00,
            'status' => 'pending',
            'payment_status' => 'unshipped',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_store_falls_back_to_variant_price_when_product_price_is_zero(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = $this->makeProduct(['price' => 0.00]);
        $product->variants()->create([
            'sku' => 'VARIANT-' . uniqid(),
            'price' => 15.00,
        ]);

        $response = $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => '456 Oak Ave',
            'payment_method' => 'card',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201);

        $customer = Customer::where('email', $user->email)->first();

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'subtotal' => 15.00,
        ]);
    }

    public function test_store_maps_payment_status_and_status_to_internal_db_values(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = $this->makeProduct(['price' => 10.00]);

        $response = $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => '456 Oak Ave',
            'payment_method' => 'card',
            'payment_status' => 'successful',
            'status' => 'delivered',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201);

        $customer = Customer::where('email', $user->email)->first();

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'status' => 'paid',
            'payment_status' => 'fulfilled',
        ]);
    }

    public function test_store_uses_default_shipping_cost_when_not_provided(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = $this->makeProduct(['price' => 10.00]);

        $response = $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => '456 Oak Ave',
            'payment_method' => 'card',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.shipping_cost', 5);
    }

    public function test_store_requires_authentication(): void
    {
        $product = $this->makeProduct();

        $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => '456 Oak Ave',
            'payment_method' => 'card',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertStatus(401);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/shop/orders', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_address', 'payment_method', 'items']);
    }

    public function test_store_validates_product_id_exists(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => '456 Oak Ave',
            'payment_method' => 'card',
            'items' => [
                ['product_id' => 999999, 'quantity' => 1],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_show_returns_order_for_owning_customer(): void
    {
        $user = User::factory()->create();
        $customer = $this->makeCustomerForUser($user);
        $order = $this->makeOrder($customer);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/shop/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_show_returns_404_when_order_does_not_exist(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/shop/orders/999999')
            ->assertStatus(404)
            ->assertJson(['code' => 'NOT_FOUND']);
    }

    public function test_show_returns_401_for_non_owning_non_admin_user(): void
    {
        $owner = User::factory()->create();
        $ownerCustomer = $this->makeCustomerForUser($owner);
        $order = $this->makeOrder($ownerCustomer);

        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/shop/orders/{$order->id}")
            ->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHORIZED']);
    }

    public function test_show_requires_authentication(): void
    {
        $owner = User::factory()->create();
        $ownerCustomer = $this->makeCustomerForUser($owner);
        $order = $this->makeOrder($ownerCustomer);

        $this->getJson("/api/v1/shop/orders/{$order->id}")
            ->assertStatus(401);
    }

    public function test_customer_orders_returns_orders_for_owning_customer(): void
    {
        $user = User::factory()->create();
        $customer = $this->makeCustomerForUser($user);
        $order = $this->makeOrder($customer);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/shop/customers/{$customer->id}/orders")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $order->id);
    }

    public function test_customer_orders_returns_403_for_non_owning_non_admin_user(): void
    {
        $owner = User::factory()->create();
        $ownerCustomer = $this->makeCustomerForUser($owner);
        $this->makeOrder($ownerCustomer);

        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/shop/customers/{$ownerCustomer->id}/orders")
            ->assertStatus(403)
            ->assertJson(['code' => 'FORBIDDEN']);
    }

    public function test_customer_orders_requires_authentication(): void
    {
        $owner = User::factory()->create();
        $ownerCustomer = $this->makeCustomerForUser($owner);

        $this->getJson("/api/v1/shop/customers/{$ownerCustomer->id}/orders")
            ->assertStatus(401);
    }

}
