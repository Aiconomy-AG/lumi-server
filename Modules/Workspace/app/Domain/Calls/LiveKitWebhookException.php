<?php

namespace Modules\Workspace\Domain\Calls;

use RuntimeException;

class LiveKitWebhookException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
    ) {
        parent::__construct($message);
    }

    public static function unauthorized(string $message = 'Invalid LiveKit webhook signature.'): self
    {
        return new self($message, 401);
    }

    public static function malformed(string $message = 'Invalid LiveKit webhook payload.'): self
    {
        return new self($message, 400);
    }
}
