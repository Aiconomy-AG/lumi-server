<?php

namespace App\Providers;

use App\Services\Search\GlobalSearchService;
use App\Services\Search\Providers\OrderSearchProvider;
use App\Services\Search\Providers\ProductSearchProvider;
use App\Services\Search\Providers\ProjectSearchProvider;
use App\Services\Search\Providers\ReturnRequestSearchProvider;
use App\Services\Search\Providers\TaskSearchProvider;
use App\Services\Search\Providers\UserSearchProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Meilisearch\Client as MeilisearchClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GlobalSearchService::class, function ($app) {
            return new GlobalSearchService(
                [
                    new TaskSearchProvider,
                    new ProjectSearchProvider,
                    new ProductSearchProvider,
                    new OrderSearchProvider,
                    new ReturnRequestSearchProvider,
                    new UserSearchProvider,
                ],
                $app->make(MeilisearchClient::class),
            );
        });
    }

    public function boot(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });
    }
}
