<?php

namespace Modules\Sales\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Exceptions\Shopify\ShopifyException;
use Modules\Sales\Integrations\Shopify\ProductSyncService;
use Modules\Sales\Models\Product;

class SyncShopifyInventoryJob implements ShouldQueue
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
        $product = Product::with('variants')->find($this->productId);

        if ($product === null || $product->variants->isEmpty()) {
            return;
        }

        try {
            $service->syncInventory($product);
        } catch (ShopifyException $exception) {
            Log::warning('Shopify inventory sync job failed', [
                'product_id' => $this->productId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
