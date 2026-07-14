<?php

namespace Modules\Workspace\Transformers;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'created_by' => $this->created_by,
            'participants' => UserResource::collection($this->whenLoaded('participants')),
            'last_message_at' => $this->messages_max_created_at
                ? Carbon::parse($this->messages_max_created_at)->toISOString()
                : ($this->relationLoaded('latestMessage') && $this->latestMessage
                    ? $this->latestMessage->created_at?->toISOString()
                    : null),
            'last_message' => $this->when(
                $this->relationLoaded('latestMessage') && $this->latestMessage,
                fn () => [
                    'message_type' => $this->latestMessage->message_type?->value ?? 'text',
                    'message' => $this->latestMessage->message,
                    'sender_id' => $this->latestMessage->sender_id,
                    'sent_at' => $this->latestMessage->created_at?->toISOString(),
                ]
            ),
        ];
    }
}
