<?php

namespace Modules\Workspace\Services;

use Modules\Workspace\Models\TaskTimeEntry;

class TimeTrackingService
{
    public function todayTotalSeconds(int $userId): int
    {
        return (int) TaskTimeEntry::query()
            ->where('user_id', $userId)
            ->whereDate('started_at', today())
            ->sum('duration_seconds');
    }
}
