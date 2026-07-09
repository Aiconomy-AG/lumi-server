<?php

namespace Modules\Workspace\Services;

use Modules\Workspace\Models\TaskTimeEntry;

class TimeTrackingService
{
    public function dailyTotalSeconds(int $userId, string $day): int
    {
        return (int) TaskTimeEntry::query()
            ->where('user_id', $userId)
            ->whereDate('started_at', $day)
            ->sum('duration_seconds');
    }
}
