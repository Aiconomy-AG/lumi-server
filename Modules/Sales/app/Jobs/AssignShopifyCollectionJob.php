<?php

namespace Modules\Sales\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Exceptions\Shopify\ShopifyException;
use Modules\Sales\Integrations\Shopify\CollectionAssignService;

class AssignShopifyCollectionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    /**
     * @param  array<int, string>  $shopifyProductIds
     */
    public function __construct(
        private readonly int $categoryId,
        private readonly array $shopifyProductIds,
    ) {
        $this->onConnection('redis');
        $this->onQueue('shopify-sync');
    }

    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }

    public function handle(CollectionAssignService $service): void
    {
        try {
            $service->assignProducts($this->categoryId, $this->shopifyProductIds);
        } catch (ShopifyException $exception) {
            Log::warning('Shopify collection assignment job failed', [
                'category_id' => $this->categoryId,
                'product_count' => count($this->shopifyProductIds),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
