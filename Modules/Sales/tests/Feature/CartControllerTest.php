<?php

namespace Modules\Sales\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Sales\Models\Cart;
use Modules\Sales\Models\CartItem;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\Product;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartControllerTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Customer is not an App\Models\User and is not currently
         * authenticated through Sanctum.
         *
         * These tests focus on cart routes, controllers, validation,
         * services and database behaviour.
         */
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

        $product = Product::factory()->create();

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
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
                'data.items.0.product_id',
                $product->id
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                2
            );
    }

    #[Test]
    public function customer_can_add_a_product_to_their_cart(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart/items",
            [
                'product_id' => $product->id,
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
                'data.items.0.product_id',
                $product->id
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
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
    }

    #[Test]
    public function adding_the_same_product_again_increases_its_quantity(): void
    {
        $product = Product::factory()->create();

        $url = "/api/v1/shop/customers/{$this->customer->id}/cart/items";

        $this->postJson($url, [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->postJson($url, [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertOk();

        $cart = Cart::query()
            ->where('customer_id', $this->customer->id)
            ->firstOrFail();

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
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

        $product = Product::factory()->create();

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->putJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart/items/{$product->id}",
            [
                'quantity' => 4,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.product_id',
                $product->id
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                4
            );

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 4,
        ]);
    }

    #[Test]
    public function customer_can_remove_an_item_from_their_cart(): void
    {
        $cart = Cart::create([
            'customer_id' => $this->customer->id,
        ]);

        $product = Product::factory()->create();

        $cartItem = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->deleteJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart/items/{$product->id}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);
    }

    #[Test]
    public function cart_item_requires_an_existing_product(): void
    {
        $response = $this->postJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart/items",
            [
                'product_id' => 999999,
                'quantity' => 1,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }

    #[Test]
    public function cart_item_quantity_must_be_at_least_one(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart/items",
            [
                'product_id' => $product->id,
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
        $product = Product::factory()->create();

        $this->putJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart/items/{$product->id}",
            [
                'quantity' => 2,
            ]
        )->assertNotFound();
    }

    #[Test]
    public function removing_a_missing_cart_item_returns_not_found(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson(
            "/api/v1/shop/customers/{$this->customer->id}/cart/items/{$product->id}"
        )->assertNotFound();
    }
}
