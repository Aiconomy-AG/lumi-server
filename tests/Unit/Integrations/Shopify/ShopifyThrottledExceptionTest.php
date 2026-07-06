<?php

namespace Tests\Unit\Integrations\Shopify;

use App\Exceptions\Shopify\ShopifyThrottledException;
use PHPUnit\Framework\TestCase;

class ShopifyThrottledExceptionTest extends TestCase
{
    public function test_it_calculates_retry_delay_from_throttle_status(): void
    {
        $delay = ShopifyThrottledException::retryDelay([
            'currentlyAvailable' => 10,
            'restoreRate' => 50,
            'requestedQueryCost' => 100,
        ], 100);

        $this->assertSame(2, $delay);
    }

    public function test_it_returns_minimum_delay_when_points_are_available(): void
    {
        $delay = ShopifyThrottledException::retryDelay([
            'currentlyAvailable' => 200,
            'restoreRate' => 50,
            'requestedQueryCost' => 100,
        ], 100);

        $this->assertSame(1, $delay);
    }

    public function test_it_exposes_retry_after_seconds(): void
    {
        $exception = new ShopifyThrottledException('throttled', 12);

        $this->assertSame(12, $exception->retryAfterSeconds());
    }
}
