<?php

namespace Modules\Workspace\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Workspace\Services\CallService;

class ExpireUnansweredCallJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $callId,
        public readonly array $participantUserIds = [],
    ) {}

    public function handle(CallService $calls): void
    {
        $calls->markMissed($this->callId, $this->participantUserIds);
    }
}
