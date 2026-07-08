<?php

namespace Modules\Sales\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\WishlistItem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WishlistControllerTest extends TestCase
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
    public function customer_can_view_their_wishlist(): void
    {
        $product = Product::factory()->create();

        WishlistItem::create([
            'customer_id' => $this->customer->id,
            'product_id' => $product->id,
        ]);

        $response = $this->getJson(
            "/api/v1/shop/customers/{$this->customer->id}/wishlist"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id);
    }

    #[Test]
    public function customer_can_add_a_product_to_their_wishlist(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson(
            "/api/v1/shop/customers/{$this->customer->id}/wishlist",
            [
                'product_id' => $product->id,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id);

        $this->assertDatabaseHas('wishlist_items', [
            'customer_id' => $this->customer->id,
            'product_id' => $product->id,
        ]);
    }

    #[Test]
    public function adding_the_same_product_twice_does_not_create_a_duplicate(): void
    {
        $product = Product::factory()->create();

        $url = "/api/v1/shop/customers/{$this->customer->id}/wishlist";

        $this->postJson($url, [
            'product_id' => $product->id,
        ])->assertOk();

        $this->postJson($url, [
            'product_id' => $product->id,
        ])->assertOk();

        $this->assertDatabaseCount('wishlist_items', 1);

        $this->assertDatabaseHas('wishlist_items', [
            'customer_id' => $this->customer->id,
            'product_id' => $product->id,
        ]);
    }

    #[Test]
    public function customer_can_remove_a_product_from_their_wishlist(): void
    {
        $product = Product::factory()->create();

        $wishlistItem = WishlistItem::create([
            'customer_id' => $this->customer->id,
            'product_id' => $product->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/shop/customers/{$this->customer->id}/wishlist/{$product->id}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseMissing('wishlist_items', [
            'id' => $wishlistItem->id,
        ]);
    }

    #[Test]
    public function wishlist_requires_an_existing_product(): void
    {
        $response = $this->postJson(
            "/api/v1/shop/customers/{$this->customer->id}/wishlist",
            [
                'product_id' => 999999,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }

    #[Test]
    public function wishlist_requires_a_product_id(): void
    {
        $response = $this->postJson(
            "/api/v1/shop/customers/{$this->customer->id}/wishlist",
            []
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }

    #[Test]
    public function removing_a_product_not_in_the_wishlist_returns_not_found(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson(
            "/api/v1/shop/customers/{$this->customer->id}/wishlist/{$product->id}"
        )->assertNotFound();
    }

    #[Test]
    public function viewing_an_empty_wishlist_returns_an_empty_collection(): void
    {
        $this->getJson(
            "/api/v1/shop/customers/{$this->customer->id}/wishlist"
        )
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
