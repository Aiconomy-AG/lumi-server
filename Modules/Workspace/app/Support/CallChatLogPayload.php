<?php

namespace Modules\Workspace\Support;

use Modules\Workspace\Models\Call;

class CallChatLogPayload
{
    public static function make(Call $call): array
    {
        $durationSeconds = null;
        if ($call->started_at !== null && $call->ended_at !== null) {
            $durationSeconds = $call->started_at->diffInSeconds($call->ended_at);
        }

        return [
            'id' => $call->id,
            'status' => $call->status->value,
            'type' => $call->callTypeValue(),
            'mode' => $call->callModeValue(),
            'duration_seconds' => $durationSeconds,
            'initiated_by_user_id' => $call->initiated_by_user_id,
            'caller_name' => $call->caller_name,
            'answered_at' => $call->answered_at?->toISOString(),
            'started_at' => $call->started_at?->toISOString(),
            'ended_at' => $call->ended_at?->toISOString(),
        ];
    }

    public static function preview(Call $call): string
    {
        $label = $call->callTypeValue() === 'video' ? 'Video call' : 'Call';

        return match ($call->status->value) {
            'missed' => 'Missed '.$label,
            'declined' => 'Declined '.$label,
            'cancelled' => 'Cancelled '.$label,
            'failed' => 'Failed '.$label,
            default => self::endedPreview($call, $label),
        };
    }

    private static function endedPreview(Call $call, string $label): string
    {
        if ($call->started_at !== null && $call->ended_at !== null) {
            $minutes = max(1, (int) ceil($call->started_at->diffInSeconds($call->ended_at) / 60));

            return $label.' · '.$minutes.' min';
        }

        return $label;
    }
}
