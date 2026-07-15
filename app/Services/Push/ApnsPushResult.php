<?php

namespace App\Services\Push;

final class ApnsPushResult
{
    public function __construct(
        public readonly int $statusCode,
        public readonly ?string $apnsId,
        public readonly ?string $reason,
        public readonly bool $success,
        public readonly bool $shouldInvalidateToken,
    ) {}
}
