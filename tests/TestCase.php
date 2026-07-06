<?php

namespace Tests;

use App\Integrations\Shopify\ShopifyAccessTokenProvider;
use App\Integrations\Shopify\ShopifyConfig;
use App\Integrations\Shopify\ShopifyConnector;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function configureShopify(array $overrides = []): void
    {
        config([
            'services.shopify' => array_merge([
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
                'shop' => 'test-shop.myshopify.com',
                'api_version' => '2026-07',
            ], $overrides),
        ]);

        $this->app->forgetInstance(ShopifyConfig::class);
        $this->app->forgetInstance(ShopifyAccessTokenProvider::class);
        $this->app->forgetInstance(ShopifyConnector::class);

        $this->app->when(ShopifyAccessTokenProvider::class)
            ->needs(CacheRepository::class)
            ->give(fn () => Cache::store('array'));
    }
}
