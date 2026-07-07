<?php

namespace Modules\Sales\Tests\Unit\Integrations\Shopify;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Sales\Exceptions\Shopify\ShopifyException;
use Modules\Sales\Integrations\Shopify\ShopifyAccessTokenProvider;
use Modules\Sales\Integrations\Shopify\ShopifyConfig;
use Modules\Sales\Tests\TestCase;

class ShopifyAccessTokenProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureShopify();
        Cache::store('array')->flush();
    }

    public function test_it_acquires_an_access_token(): void
    {
        Http::fake([
            'test-shop.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_test_token',
                'expires_in' => 3600,
            ]),
        ]);

        $token = app(ShopifyAccessTokenProvider::class)->getAccessToken();

        $this->assertSame('shpat_test_token', $token);
    }

    public function test_it_caches_the_access_token(): void
    {
        Http::fake([
            'test-shop.myshopify.com/admin/oauth/access_token' => Http::response([
                'access_token' => 'shpat_cached_token',
                'expires_in' => 3600,
            ]),
        ]);

        $provider = app(ShopifyAccessTokenProvider::class);

        $provider->getAccessToken();
        $provider->getAccessToken();

        Http::assertSentCount(1);
    }

    public function test_it_refreshes_the_token_after_invalidation(): void
    {
        Http::fake([
            'test-shop.myshopify.com/admin/oauth/access_token' => Http::sequence()
                ->push(['access_token' => 'shpat_first', 'expires_in' => 3600])
                ->push(['access_token' => 'shpat_second', 'expires_in' => 3600]),
        ]);

        $provider = app(ShopifyAccessTokenProvider::class);

        $this->assertSame('shpat_first', $provider->getAccessToken());

        $provider->invalidate();

        $this->assertSame('shpat_second', $provider->getAccessToken());
        Http::assertSentCount(2);
    }

    public function test_it_throws_when_token_acquisition_fails(): void
    {
        Http::fake([
            'test-shop.myshopify.com/admin/oauth/access_token' => Http::response([
                'error' => 'app_not_installed',
                'error_description' => 'The application is not installed on this shop.',
            ], 400),
        ]);

        $this->expectException(ShopifyException::class);
        $this->expectExceptionMessage('app_not_installed');

        app(ShopifyAccessTokenProvider::class)->getAccessToken();
    }

    public function test_it_validates_shop_hostname(): void
    {
        $this->configureShopify(['shop' => 'https://bad-shop.myshopify.com']);

        $this->expectException(ShopifyException::class);
        $this->expectExceptionMessage('Invalid SHOPIFY_SHOP format.');

        new ShopifyConfig;
    }
}
