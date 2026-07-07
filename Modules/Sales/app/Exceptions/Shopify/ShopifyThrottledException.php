<?php

namespace Modules\Sales\Exceptions\Shopify;

class ShopifyThrottledException extends ShopifyException
{
    public function __construct(
        string $message,
        private readonly int $retryAfterSeconds,
    ) {
        parent::__construct($message);
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }

    public static function retryDelay(array $throttleStatus, int $requestedQueryCost = 0): int
    {
        $available = (int) ($throttleStatus['currentlyAvailable'] ?? 0);
        $restoreRate = (float) ($throttleStatus['restoreRate'] ?? 0);
        $cost = $requestedQueryCost > 0
            ? $requestedQueryCost
            : (int) ($throttleStatus['requestedQueryCost'] ?? 1);

        if ($available >= $cost) {
            return 1;
        }

        if ($restoreRate <= 0) {
            return 2;
        }

        return max(1, (int) ceil(($cost - $available) / $restoreRate));
    }
}
