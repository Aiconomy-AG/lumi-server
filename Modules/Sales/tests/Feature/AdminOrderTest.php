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

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_orders_index_returns_paginated_data_with_customer(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $customer = Customer::create([
            'username' => 'customer1',
            'email' => 'customer@example.com',
            'shopify_customer_id' => 'shop_1',
        ]);

        $product = Product::create([
            'name' => 'Ordered Product',
            'price' => 12.5,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'subtotal' => 12.5,
            'shipping_cost' => 2.5,
            'total_amount' => 15,
            'shipping_address' => 'Address',
            'payment_method' => 'card',
            'payment_status' => 'unshipped',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'customer', 'created_at', 'items'],
                ],
                'meta',
            ]);
    }
}
