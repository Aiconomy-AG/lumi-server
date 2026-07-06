<?php

namespace App\Providers;

use App\Integrations\Shopify\ShopifyAccessTokenProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->when(ShopifyAccessTokenProvider::class)
            ->needs(CacheRepository::class)
            ->give(fn () => Cache::store('redis'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
