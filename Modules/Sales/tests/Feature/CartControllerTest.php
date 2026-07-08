<?php

namespace Modules\Sales\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Sales\Models\Cart;
use Modules\Sales\Models\CartItem;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\ProductVariant;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartControllerTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        $this->customer = Customer::factory()->create([
            'username' => 'Shopify Customer',
            'email' => 'customer@example.com',
            'shopify_customer_id' => 'gid://shopify/Customer/1001',
        ]);
    }

    #[Test]
    public function customer_can_view_their_cart(): void
    {
        $cart = Cart::create([
            'customer_id' => $this->customer->id,
        ]);

        $variant = ProductVariant::factory()->create();

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $this->getJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.customer_id',
                $this->customer->id
            )
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath(
                'data.items.0.product_variant_id',
                $variant->id
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                2
            );
    }

    #[Test]
    public function customer_can_add_a_product_variant_to_their_cart(): void
    {
        $variant = ProductVariant::factory()->create();

        $response = $this->postJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart/items",
            [
                'product_variant_id' => $variant->id,
                'quantity' => 3,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.customer_id',
                $this->customer->id
            )
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath(
                'data.items.0.product_variant_id',
                $variant->id
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                3
            );

        $cart = Cart::query()
            ->where('customer_id', $this->customer->id)
            ->firstOrFail();

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);
    }

    #[Test]
    public function adding_the_same_product_variant_again_increases_its_quantity(): void
    {
        $variant = ProductVariant::factory()->create();

        $url = "/api/v1/shop/customers/{$this->customer->id}/cart/items";

        $this->postJson($url, [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->postJson($url, [
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ])->assertOk();

        $cart = Cart::query()
            ->where('customer_id', $this->customer->id)
            ->firstOrFail();

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
        ]);

        $this->assertDatabaseCount('cart_items', 1);
    }

    #[Test]
    public function customer_can_update_cart_item_quantity(): void
    {
        $cart = Cart::create([
            'customer_id' => $this->customer->id,
        ]);

        $variant = ProductVariant::factory()->create();

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $response = $this->putJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart/items/{$variant->id}",
            [
                'quantity' => 4,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.product_variant_id',
                $variant->id
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                4
            );

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 4,
        ]);
    }

    #[Test]
    public function customer_can_remove_an_item_from_their_cart(): void
    {
        $cart = Cart::create([
            'customer_id' => $this->customer->id,
        ]);

        $variant = ProductVariant::factory()->create();

        $cartItem = CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $response = $this->deleteJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart/items/{$variant->id}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);
    }

    #[Test]
    public function cart_item_requires_an_existing_product_variant(): void
    {
        $response = $this->postJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart/items",
            [
                'product_variant_id' => 999999,
                'quantity' => 1,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_variant_id');
    }

    #[Test]
    public function cart_item_quantity_must_be_at_least_one(): void
    {
        $variant = ProductVariant::factory()->create();

        $response = $this->postJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart/items",
            [
                'product_variant_id' => $variant->id,
                'quantity' => 0,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    #[Test]
    public function updating_a_missing_cart_item_returns_not_found(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->putJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart/items/{$variant->id}",
            [
                'quantity' => 2,
            ]
        )->assertNotFound();
    }

    #[Test]
    public function removing_a_missing_cart_item_returns_not_found(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->deleteJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart/items/{$variant->id}"
        )->assertNotFound();
    }
}
