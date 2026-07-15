<?php

namespace Modules\Workspace\Services\AiChat;

class ProposedAction
{
    public function __construct(
        public readonly string $toolName,
        public readonly array $arguments,
        public readonly string $summary,
    ) {}
}
