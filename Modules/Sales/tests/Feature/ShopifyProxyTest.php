<?php

namespace Modules\Sales\Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ReturnRequest;
use Tests\TestCase;

class ShopifyProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sales.shopify.client_secret' => 'proxy-secret']);
    }

    public function test_wishlist_proxy_requires_valid_signature(): void
    {
        $this->getJson('/api/v1/shopify/proxy/wishlist?shop=test.myshopify.com&logged_in_customer_id=123&signature=bad')
            ->assertUnauthorized();
    }

    public function test_wishlist_proxy_saves_and_removes_synced_products(): void
    {
        Product::query()->create([
            'name' => 'Bath Bomb',
            'price' => 10,
            'shopify_product_id' => 'gid://shopify/Product/999',
        ]);

        $query = $this->signedProxyQuery([
            'shop' => 'test.myshopify.com',
            'logged_in_customer_id' => '123',
            'timestamp' => '1234567890',
        ]);

        $this->postJson('/api/v1/shopify/proxy/wishlist/items?'.$query, [
            'shopify_product_id' => '999',
        ])->assertCreated()->assertJsonPath('saved', true);

        $this->assertDatabaseHas('customers', ['shopify_customer_id' => '123']);
        $this->assertDatabaseCount('wishlist_items', 1);

        $this->deleteJson('/api/v1/shopify/proxy/wishlist/items/999?'.$query)
            ->assertOk()
            ->assertJsonPath('saved', false);

        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_return_proxy_creates_return_request(): void
    {
        $query = $this->signedProxyQuery([
            'shop' => 'test.myshopify.com',
            'logged_in_customer_id' => '456',
            'timestamp' => '1234567890',
        ]);

        $this->postJson('/api/v1/shopify/proxy/returns?'.$query, [
            'order_identifier' => '1001',
            'email' => 'customer@example.com',
            'reason' => 'damaged',
            'notes' => 'Arrived broken',
            'items' => [
                [
                    'title' => 'Bath Bomb',
                    'quantity' => 1,
                    'unit_price' => 10,
                ],
            ],
        ])->assertCreated()->assertJsonPath('data.status', 'requested');

        $this->assertDatabaseHas('return_requests', [
            'shopify_customer_id' => '456',
            'shopify_order_name' => '1001',
            'reason' => 'damaged',
        ]);
    }

    public function test_staff_can_manage_return_requests(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employee]));

        $customer = Customer::query()->create([
            'username' => 'Shopify Customer',
            'email' => null,
            'shopify_customer_id' => '456',
        ]);

        $returnRequest = ReturnRequest::query()->create([
            'customer_id' => $customer->id,
            'shopify_customer_id' => '456',
            'shopify_order_name' => '#1001',
            'reason' => 'damaged',
            'status' => 'requested',
        ]);

        $this->getJson('/api/v1/admin/returns')->assertOk();

        $this->postJson("/api/v1/admin/returns/{$returnRequest->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->patchJson("/api/v1/admin/returns/{$returnRequest->id}", [
            'notes' => 'Approved by support',
        ])->assertOk()->assertJsonPath('data.notes', 'Approved by support');
    }

    private function signedProxyQuery(array $parameters): string
    {
        $pairs = [];

        foreach ($parameters as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        sort($pairs, SORT_STRING);
        $parameters['signature'] = hash_hmac('sha256', implode('', $pairs), 'proxy-secret');

        return http_build_query($parameters);
    }
}
