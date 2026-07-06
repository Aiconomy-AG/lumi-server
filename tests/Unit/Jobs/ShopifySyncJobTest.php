<?php

namespace Tests\Unit\Jobs;

use App\Exceptions\Shopify\ShopifyThrottledException;
use App\Integrations\Shopify\ShopifyConnector;
use App\Jobs\ShopifySyncJob;
use Mockery;
use Tests\TestCase;

class ShopifySyncJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_uses_redis_connection_and_shopify_sync_queue(): void
    {
        $job = new ShopifySyncJob([
            'query' => 'query { shop { name } }',
        ]);

        $this->assertSame('redis', $job->connection);
        $this->assertSame('shopify-sync', $job->queue);
        $this->assertSame(5, $job->tries);
        $this->assertSame(55, $job->timeout);
        $this->assertSame([30, 60, 120, 300, 600], $job->backoff());
    }

    public function test_it_releases_the_job_when_throttled(): void
    {
        $connector = Mockery::mock(ShopifyConnector::class);
        $connector->shouldReceive('query')
            ->once()
            ->andThrow(new ShopifyThrottledException('throttled', 15));

        $job = new class (['query' => 'query { shop { name } }']) extends ShopifySyncJob
        {
            public ?int $releasedAfter = null;

            public function release($delay = 0, $backoff = true): void
            {
                $this->releasedAfter = (int) $delay;
            }
        };

        $job->handle($connector);

        $this->assertSame(15, $job->releasedAfter);
    }
}
