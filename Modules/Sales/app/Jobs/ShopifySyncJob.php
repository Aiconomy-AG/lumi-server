<?php

namespace Modules\Sales\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Sales\Exceptions\Shopify\ShopifyThrottledException;
use Modules\Sales\Integrations\Shopify\ShopifyConnector;

class ShopifySyncJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 55;

    public function __construct(
        private readonly array $payload,
    ) {
        $this->onConnection('redis');
        $this->onQueue('shopify-sync');
    }

    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }

    public function handle(ShopifyConnector $connector): void
    {
        try {
            $connector->query($this->payload);
        } catch (ShopifyThrottledException $exception) {
            $this->release($exception->retryAfterSeconds());
        }
    }
}
