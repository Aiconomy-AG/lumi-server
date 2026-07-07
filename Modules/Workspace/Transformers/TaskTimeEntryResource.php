<?php
namespace Modules\Workspace\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskTimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'employee_id' => $this->user_id,
            'started_at' => $this->started_at ? $this->started_at->toIso8601String() : null,
            'stopped_at' => $this->stopped_at ? $this->stopped_at->toIso8601String() : null,
            'duration_seconds' => $this->duration_seconds ? (int) $this->duration_seconds : null,
        ];
    }
}
