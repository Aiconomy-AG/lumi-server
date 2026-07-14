<?php

namespace Modules\Workspace\Support;

use Modules\Workspace\Models\Call;

class CallPayload
{
    public static function make(Call $call): array
    {
        $call->loadMissing(['participants.user']);

        return [
            'id' => $call->id,
            'conversation_id' => $call->conversation_id,
            'initiated_by_user_id' => $call->initiated_by_user_id,
            'caller' => [
                'id' => $call->initiated_by_user_id,
                'name' => $call->caller_name,
                'phone_number' => $call->caller_phone_number,
            ],
            'participants' => $call->participants->map(fn ($participant): array => [
                'user_id' => $participant->user_id,
                'name' => $participant->user?->name,
                'role' => $participant->role,
                'status' => $participant->status->value,
            ])->values()->all(),
            'media_type' => $call->media_type,
            'status' => $call->status->value,
            'answered_client_instance_id' => $call->answered_client_instance_id,
            'end_reason' => $call->end_reason,
            'answered_at' => $call->answered_at?->toISOString(),
            'ended_at' => $call->ended_at?->toISOString(),
            'created_at' => $call->created_at?->toISOString(),
            'updated_at' => $call->updated_at?->toISOString(),
        ];
    }
}
