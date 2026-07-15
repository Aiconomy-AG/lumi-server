<?php

namespace Modules\Workspace\Support;

use Modules\Workspace\Models\Call;

class CallPayload
{
    public static function make(Call $call, ?array $connection = null): array
    {
        $call->loadMissing(['participants.user']);

        $payload = [
            'id' => $call->id,
            'conversation_id' => $call->conversation_id,
            'initiated_by_user_id' => $call->initiated_by_user_id,
            'destination_type' => $call->destination_type,
            'room_name' => $call->room_name,
            'caller' => [
                'id' => $call->initiated_by_user_id,
                'name' => $call->caller_name,
            ],
            'participants' => $call->participants->map(fn ($participant): array => [
                'user_id' => $participant->user_id,
                'name' => $participant->user?->name,
                'role' => $participant->role,
                'status' => $participant->status->value,
                'invited_at' => $participant->invited_at?->toISOString(),
                'ringing_delivered_at' => $participant->ringing_delivered_at?->toISOString(),
                'answered_at' => $participant->answered_at?->toISOString(),
                'joined_at' => $participant->joined_at?->toISOString(),
                'left_at' => $participant->left_at?->toISOString(),
            ])->values()->all(),
            'mode' => $call->callModeValue(),
            'type' => $call->callTypeValue(),
            'media_type' => $call->media_type,
            'status' => $call->status->value,
            'answered_client_instance_id' => $call->answered_client_instance_id,
            'end_reason' => $call->end_reason,
            'answered_at' => $call->answered_at?->toISOString(),
            'started_at' => $call->started_at?->toISOString(),
            'ended_at' => $call->ended_at?->toISOString(),
            'created_at' => $call->created_at?->toISOString(),
            'updated_at' => $call->updated_at?->toISOString(),
        ];

        if ($connection !== null) {
            $payload['connection'] = $connection;
        }

        return $payload;
    }
}
