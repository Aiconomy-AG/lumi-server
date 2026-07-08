<?php

namespace Modules\Sales\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Sales\Integrations\Shopify\CollectionAssignService;

#[Signature('sales:assign-shopify-collections {--sync : Assign collections synchronously instead of queueing jobs}')]
#[Description('Assign existing synced products to Shopify collections based on local category mapping')]
class AssignShopifyCollections extends Command
{
    public function handle(CollectionAssignService $service): int
    {
        if ($this->option('sync')) {
            $stats = $service->assignAll();

            $this->components->info(sprintf(
                'Assigned %d products (%d failed).',
                $stats['assigned'],
                $stats['failed'],
            ));

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $queued = $service->queueAssign();

        $this->components->info(sprintf(
            'Queued %d collection assignment job(s). Run a worker to process them: php artisan queue:work redis --queue=shopify-sync',
            $queued,
        ));

        return self::SUCCESS;
    }
}
