<?php

namespace Modules\Sales\Tests\Feature\Console;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Sales\Tests\TestCase;

class ShopifyTestConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureShopify();
        Cache::store('array')->flush();
    }

    public function test_command_reports_success(): void
    {
        Http::fake([
            'test-sales.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_test',
                'expires_in' => 3600,
            ]),
            'test-sales.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'data' => [
                    'sales' => [
                        'name' => 'Test Shop',
                        'myshopifyDomain' => 'test-sales.myshopify.com',
                    ],
                ],
                'extensions' => [],
            ]),
        ]);

        $this->artisan('shopify:test-connection')
            ->expectsOutputToContain('Connected to Test Shop (test-sales.myshopify.com)')
            ->assertSuccessful();
    }

    public function test_command_reports_failure(): void
    {
        Http::fake([
            'test-sales.myshopify.com/admin/oauth/access_token' => Http::response('invalid', 400),
        ]);

        $this->artisan('shopify:test-connection')
            ->expectsOutputToContain('Token request failed (HTTP 400).')
            ->assertFailed();
    }

    public function test_command_reports_graphql_failure_details(): void
    {
        Http::fake([
            'test-sales.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_test',
                'expires_in' => 3600,
            ]),
            'test-sales.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'errors' => [
                    [
                        'message' => 'Access denied for sales field.',
                        'extensions' => ['code' => 'ACCESS_DENIED'],
                    ],
                ],
            ]),
        ]);

        $this->artisan('shopify:test-connection')
            ->expectsOutputToContain('Access denied for sales field.')
            ->assertFailed();
    }
}
