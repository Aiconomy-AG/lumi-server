<?php

namespace Modules\Sales\Tests;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Modules\Sales\Integrations\Shopify\ShopifyAccessTokenProvider;
use Modules\Sales\Integrations\Shopify\ShopifyConfig;
use Modules\Sales\Integrations\Shopify\ShopifyConnector;

abstract class TestCase extends BaseTestCase
{
    protected function configureShopify(array $overrides = []): void
    {
        config([
            'sales.shopify' => array_merge([
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
                'shop' => 'test-sales.myshopify.com',
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
