<?php

namespace Modules\Workspace\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiActionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'message_id' => $this->message_id,
            'tool_name' => $this->tool_name,
            'summary' => $this->summary,
            'arguments' => $this->arguments,
            'status' => $this->status?->value ?? $this->status,
            'expires_at' => $this->expires_at?->toISOString(),
            'executed_at' => $this->executed_at?->toISOString(),
            'result' => $this->result,
            'error' => $this->error,
        ];
    }
}
