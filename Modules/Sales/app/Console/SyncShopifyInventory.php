<?php

namespace Modules\Sales\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Sales\Integrations\Shopify\ProductSyncService;

#[Signature('sales:sync-shopify-inventory {--sync : Push inventory synchronously instead of queueing jobs}')]
#[Description('Push local variant stock quantities to Shopify for products already linked to Shopify')]
class SyncShopifyInventory extends Command
{
    public function handle(ProductSyncService $shopify): int
    {
        if ($this->option('sync')) {
            $stats = $shopify->syncAllInventory();

            $this->components->info(sprintf(
                'Synced inventory for %d products (%d failed, %d skipped).',
                $stats['synced'],
                $stats['failed'],
                $stats['skipped'],
            ));

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $queued = $shopify->queueInventorySync();

        $this->components->info(sprintf(
            'Queued %d inventory sync job(s). Run a worker on shopify-sync: php artisan queue:work redis --queue=shopify-sync',
            $queued,
        ));

        return self::SUCCESS;
    }
}
