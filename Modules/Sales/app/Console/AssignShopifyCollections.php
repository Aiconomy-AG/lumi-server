<?php

namespace Modules\Sales\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Sales\Integrations\Shopify\CollectionAssignService;

#[Signature('sales:assign-shopify-collections {--sync : Assign collections synchronously instead of queueing jobs}')]
#[Description('Reconcile Shopify collection membership from local product categories (read-only locally)')]
class AssignShopifyCollections extends Command
{
    public function handle(CollectionAssignService $service): int
    {
        if ($this->option('sync')) {
            $stats = $service->reconcileAll();

            $this->components->info(sprintf(
                'Reconciled collections: %d product placements added, %d stale placements removed (%d categories failed).',
                $stats['assigned'],
                $stats['removed'],
                $stats['failed'],
            ));

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $queued = $service->queueAssign();

        $this->components->info(sprintf(
            'Queued %d collection reconciliation job(s). Run a worker to process them: php artisan queue:work redis --queue=shopify-sync',
            $queued,
        ));

        return self::SUCCESS;
    }
}
