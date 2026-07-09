<?php

namespace Modules\Workspace\Transformers;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $event = $this->event;

        return [
            'id' => $this->id,
            'notification_event_id' => $this->notification_event_id,
            'recipient_user_id' => $this->recipient_user_id,
            'read_at' => $this->read_at,
            'seen_at' => $this->seen_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'event' => [
                'id' => $event->id,
                'actor_user_id' => $event->actor_user_id,
                'actor' => $event->relationLoaded('actor') && $event->actor
                    ? new UserResource($event->actor)
                    : null,
                'type' => $event->type,
                'source' => $event->source,
                'task_id' => $event->task_id,
                'conversation_id' => $event->conversation_id,
                'message_id' => $event->message_id,
                'payload' => $event->payload,
                'created_at' => $event->created_at,
                'updated_at' => $event->updated_at,
            ],
        ];
    }
}
