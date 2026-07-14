<?php

namespace Modules\Workspace\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Workspace\Domain\Messages\MessageType;
use Modules\Workspace\Support\CallChatLogPayload;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $messageType = $this->message_type?->value ?? MessageType::Text->value;
        $reactions = $this->relationLoaded('reactions')
            ? $this->reactions
                ->groupBy('emoji')
                ->map(fn ($items, string $emoji): array => [
                    'emoji' => $emoji,
                    'count' => $items->count(),
                    'user_ids' => $items->pluck('user_id')->map(fn ($userId): int => (int) $userId)->values()->all(),
                ])
                ->values()
                ->all()
            : [];

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'message_type' => $messageType,
            'message' => $this->message,
            'type' => $this->type ?? 'text',
            'meta' => $this->meta,
            'reactions' => $reactions,
            'sent_at' => $this->created_at?->toISOString(),
            'call' => $this->when(
                $messageType === MessageType::Call->value && $this->relationLoaded('call') && $this->call,
                fn () => CallChatLogPayload::make($this->call),
            ),
        ];
    }
}
