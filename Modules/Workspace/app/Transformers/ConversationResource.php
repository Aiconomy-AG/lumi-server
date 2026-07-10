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
                : null,
        ];
    }
}
