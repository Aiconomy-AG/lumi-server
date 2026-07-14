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

    public function __construct(
        public Call $call,
        public int $recipientUserId,
        public ?array $connection = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->recipientUserId)];
    }

    public function broadcastAs(): string
    {
        return 'call.updated';
    }

    public function broadcastWith(): array
    {
        return CallPayload::make($this->call, $this->connection);
    }
}
