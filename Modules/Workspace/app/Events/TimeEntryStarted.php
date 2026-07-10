<?php

namespace Modules\Workspace\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Workspace\Models\TaskTimeEntry;

class TimeEntryStarted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TaskTimeEntry $entry
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->entry->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'time-entry.started';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->entry->id,
            'task_id' => $this->entry->task_id,
            'employee_id' => $this->entry->user_id,
            'started_at' => $this->entry->started_at?->toIso8601String(),
            'stopped_at' => $this->entry->stopped_at?->toIso8601String(),
            'duration_seconds' => $this->entry->duration_seconds !== null ? (int) $this->entry->duration_seconds : null,
        ];
    }
}
