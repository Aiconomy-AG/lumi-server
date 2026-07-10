<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Workspace\Models\NotificationDelivery;

class NotificationDelivered implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public NotificationDelivery $delivery
    ) {
        $this->delivery->loadMissing(['event.actor', 'event.task', 'event.conversation']);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->delivery->recipient_user_id)];
    }

    public function broadcastAs(): string
    {
        return 'notification.delivered';
    }

    public function broadcastWith(): array
    {
        $event = $this->delivery->event;

        return [
            'id' => $this->delivery->id,
            'notification_event_id' => $event->id,
            'recipient_user_id' => $this->delivery->recipient_user_id,
            'read_at' => $this->delivery->read_at?->toIso8601String(),
            'seen_at' => $this->delivery->seen_at?->toIso8601String(),
            'dismissed_at' => $this->delivery->dismissed_at?->toIso8601String(),
            'created_at' => $this->delivery->created_at?->toIso8601String(),
            'event' => [
                'id' => $event->id,
                'actor_user_id' => $event->actor_user_id,
                'actor' => $event->actor ? [
                    'id' => $event->actor->id,
                    'name' => $event->actor->name,
                    'email' => $event->actor->email,
                ] : null,
                'type' => $event->type,
                'source' => $event->source,
                'task_id' => $event->task_id,
                'conversation_id' => $event->conversation_id,
                'message_id' => $event->message_id,
                'payload' => $event->payload,
                'created_at' => $event->created_at?->toIso8601String(),
            ],
        ];
    }
}
