<?php

namespace Modules\Sales\Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\Order;
use Modules\Sales\Models\OrderItem;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_orders_index_returns_paginated_data_with_customer(): void
    {
        Sanctum::actingAs(User::factory()->create());

        [$order] = $this->createOrderWithItem();

        $this->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'customer', 'created_at', 'items'],
                ],
                'meta',
            ])
            ->assertJsonPath('data.0.id', $order->id);
    }

    public function test_staff_can_show_order_detail(): void
    {
        Sanctum::actingAs(User::factory()->create());

        [$order] = $this->createOrderWithItem();

        $this->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'customer',
                    'items' => [
                        '*' => ['id', 'product_variant_id', 'quantity', 'unit_price', 'variant'],
                    ],
                    'return_requests',
                ],
            ]);
    }

    public function test_orders_index_filters_by_status(): void
    {
        Sanctum::actingAs(User::factory()->create());

        [$shippedOrder] = $this->createOrderWithItem([
            'status' => 'paid',
            'payment_status' => 'shipped',
        ]);

        $this->createOrderWithItem([
            'status' => 'pending',
            'payment_status' => 'unshipped',
        ]);

        $this->getJson('/api/v1/admin/orders?status=shipped')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $shippedOrder->id);
    }

    public function test_orders_index_filters_by_search(): void
    {
        Sanctum::actingAs(User::factory()->create());

        [$order] = $this->createOrderWithItem([
            'shopify_order_name' => '#1001',
        ]);

        $this->createOrderWithItem([
            'shopify_order_name' => '#2002',
        ]);

        $this->getJson('/api/v1/admin/orders?search=1001')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $order->id);
    }

    public function test_client_cannot_access_admin_orders(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Client]));

        $this->getJson('/api/v1/admin/orders')
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $orderAttributes
     * @return array{0: Order, 1: Customer}
     */
    private function createOrderWithItem(array $orderAttributes = []): array
    {
        $suffix = uniqid();

        $customer = Customer::create([
            'username' => 'customer_'.$suffix,
            'email' => "customer-{$suffix}@example.com",
            'shopify_customer_id' => 'shop_'.$suffix,
        ]);

        $product = Product::create([
            'name' => 'Ordered Product '.$suffix,
            'price' => 12.5,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.$suffix,
            'name' => 'Default',
            'price' => 12.5,
            'stock_quantity' => 10,
        ]);

        $order = Order::create(array_merge([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'subtotal' => 12.5,
            'shipping_cost' => 2.5,
            'total_amount' => 15,
            'shipping_address' => 'Address',
            'payment_method' => 'card',
            'payment_status' => 'unshipped',
        ], $orderAttributes));

        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 12.5,
        ]);

        return [$order, $customer];
    }
}
