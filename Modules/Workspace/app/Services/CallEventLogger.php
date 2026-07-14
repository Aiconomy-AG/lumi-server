<?php

namespace Modules\Workspace\Services;

use Modules\Workspace\Models\Call;
use Modules\Workspace\Models\CallEvent;

class CallEventLogger
{
    public function log(string $callId, string $eventType, array $payload = []): void
    {
        CallEvent::query()->create([
            'call_id' => $callId,
            'event_type' => $eventType,
            'payload' => $payload === [] ? null : $payload,
            'created_at' => now(),
        ]);
    }

    public function logCall(Call $call, string $eventType, array $payload = []): void
    {
        $this->log($call->id, $eventType, $payload);
    }
}
