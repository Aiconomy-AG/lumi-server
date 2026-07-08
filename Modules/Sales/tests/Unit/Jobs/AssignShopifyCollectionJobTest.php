<?php

namespace Modules\Sales\Tests\Unit\Jobs;

use Modules\Sales\Jobs\AssignShopifyCollectionJob;
use Modules\Sales\Tests\TestCase;

class AssignShopifyCollectionJobTest extends TestCase
{
    public function test_it_uses_redis_connection_and_shopify_sync_queue(): void
    {
        $job = new AssignShopifyCollectionJob(1, ['gid://shopify/Product/101']);

        $this->assertSame('redis', $job->connection);
        $this->assertSame('shopify-sync', $job->queue);
        $this->assertSame(5, $job->tries);
        $this->assertSame(120, $job->timeout);
        $this->assertSame([30, 60, 120, 300, 600], $job->backoff());
    }
}
