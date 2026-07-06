<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

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
            'test-shop.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_test',
                'expires_in' => 3600,
            ]),
            'test-shop.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
                'data' => [
                    'shop' => [
                        'name' => 'Test Shop',
                        'myshopifyDomain' => 'test-shop.myshopify.com',
                    ],
                ],
                'extensions' => [],
            ]),
        ]);

        $this->artisan('shopify:test-connection')
            ->expectsOutputToContain('Testing Shopify connection...')
            ->expectsOutputToContain('Connected to Test Shop (test-shop.myshopify.com)')
            ->assertSuccessful();
    }

    public function test_command_reports_failure(): void
    {
        Http::fake([
            'test-shop.myshopify.com/admin/oauth/access_token' => Http::response('invalid', 400),
        ]);

        $this->artisan('shopify:test-connection')
            ->expectsOutputToContain('Connection failed:')
            ->assertFailed();
    }
}
