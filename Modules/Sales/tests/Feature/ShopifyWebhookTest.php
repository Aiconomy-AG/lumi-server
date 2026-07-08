<?php

namespace Modules\Sales\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Sales\Models\Customer;
use Tests\TestCase;

class ShopifyWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sales.shopify.client_secret' => 'webhook-secret']);
    }

    public function test_webhooks_reject_invalid_hmac(): void
    {
        $this->postJson('/api/shopify/webhooks/customers-create', ['id' => 123], [
            'X-Shopify-Hmac-Sha256' => 'bad',
        ])->assertUnauthorized();
    }

    public function test_customer_webhook_upserts_customer(): void
    {
        $payload = [
            'id' => 123,
            'email' => 'customer@example.com',
            'first_name' => 'Fresh',
            'last_name' => 'Shopper',
        ];

        $this->postSignedWebhook('/api/shopify/webhooks/customers-create', $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('customers', [
            'shopify_customer_id' => '123',
            'email' => 'customer@example.com',
            'username' => 'Fresh Shopper',
        ]);
    }

    public function test_order_webhook_creates_order_for_shopify_customer(): void
    {
        Customer::query()->create([
            'username' => 'Fresh Shopper',
            'email' => 'customer@example.com',
            'shopify_customer_id' => '123',
        ]);

        $payload = [
            'id' => 987,
            'admin_graphql_api_id' => 'gid://shopify/Order/987',
            'name' => '#1001',
            'financial_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'subtotal_price' => '20.00',
            'total_price' => '25.00',
            'shipping_lines' => [['price' => '5.00']],
            'payment_gateway_names' => ['shopify_payments'],
            'customer' => ['id' => 123, 'email' => 'customer@example.com'],
            'shipping_address' => ['address1' => 'Street 1', 'city' => 'Zurich', 'country' => 'CH'],
            'line_items' => [],
        ];

        $this->postSignedWebhook('/api/shopify/webhooks/orders-create', $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('orders', [
            'shopify_order_id' => 'gid://shopify/Order/987',
            'shopify_order_name' => '#1001',
            'status' => 'paid',
            'payment_status' => 'fulfilled',
        ]);
    }

    private function postSignedWebhook(string $url, array $payload)
    {
        $json = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $json, 'webhook-secret', true));

        return $this->call(
            'POST',
            $url,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
            ],
            $json,
        );
    }
}
