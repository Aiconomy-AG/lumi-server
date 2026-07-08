<?php

namespace Modules\Sales\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Sales\Integrations\Shopify\ProductSyncService;
use Modules\Sales\Models\Product;

class SyncShopifyProductJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(
        private readonly int $productId,
    ) {
        $this->onConnection('redis');
        $this->onQueue('shopify-sync');
    }

    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }

    public function handle(ProductSyncService $service): void
    {
        $product = Product::with([
            'variants',
            'ingredients' => fn ($query) => $query->orderBy('product_ingredients.id'),
        ])->find($this->productId);

        if ($product !== null) {
            $service->sync($product);
        }
    }
}
