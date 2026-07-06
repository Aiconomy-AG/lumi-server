<?php

namespace App\Exceptions\Shopify;

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

    /**
     * @param  array<string, mixed>  $throttleStatus
     */
    public static function calculateRetryDelay(array $throttleStatus, int $requestedQueryCost = 0): int
    {
        $currentlyAvailable = (int) ($throttleStatus['currentlyAvailable'] ?? 0);
        $restoreRate = (float) ($throttleStatus['restoreRate'] ?? 0);
        $requestedQueryCost = $requestedQueryCost > 0
            ? $requestedQueryCost
            : (int) ($throttleStatus['requestedQueryCost'] ?? 1);

        if ($currentlyAvailable >= $requestedQueryCost) {
            return 1;
        }

        if ($restoreRate <= 0) {
            return 2;
        }

        $deficit = $requestedQueryCost - $currentlyAvailable;

        return max(1, (int) ceil($deficit / $restoreRate));
    }
}
