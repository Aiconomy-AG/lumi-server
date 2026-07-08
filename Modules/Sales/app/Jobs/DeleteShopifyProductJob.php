<?php

namespace Modules\Sales\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Sales\Integrations\Shopify\ProductSyncService;

class DeleteShopifyProductJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(
        private readonly string $shopifyProductId,
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
        $service->deleteRemote($this->shopifyProductId);
    }
}
