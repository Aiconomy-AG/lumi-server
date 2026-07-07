<?php

namespace Modules\Sales\Providers;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Modules\Sales\Console\ShopifySeedMockProducts;
use Modules\Sales\Console\ShopifyTestConnection;
use Modules\Sales\Integrations\Shopify\ShopifyAccessTokenProvider;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SalesServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Sales';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'sales';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ShopifyTestConnection::class,
        ShopifySeedMockProducts::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->when(ShopifyAccessTokenProvider::class)
            ->needs(CacheRepository::class)
            ->give(fn () => Cache::store('redis'));
    }
}
