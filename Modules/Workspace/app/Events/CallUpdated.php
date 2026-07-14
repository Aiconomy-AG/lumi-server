<?php

namespace Modules\Workspace\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Workspace\Models\Call;
use Modules\Workspace\Support\CallPayload;

class CallUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Call $call) {}

    public function broadcastOn(): array
    {
        $this->call->loadMissing('participants');

        return $this->call->participants
            ->map(fn ($participant) => new PrivateChannel('users.'.$participant->user_id))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'call.updated';
    }

    public function broadcastWith(): array
    {
        return CallPayload::make($this->call);
    }
}
