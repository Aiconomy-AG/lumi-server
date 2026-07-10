<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Workspace\Models\NotificationDelivery;

class NotificationDismissed implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public NotificationDelivery $delivery
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->delivery->recipient_user_id)];
    }

    public function broadcastAs(): string
    {
        return 'notification.dismissed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->delivery->id,
            'dismissed_at' => $this->delivery->dismissed_at?->toIso8601String(),
        ];
    }
}
