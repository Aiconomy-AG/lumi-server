<?php
namespace Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Workspace\Events\TimeEntryStarted;
use Modules\Workspace\Events\TimeEntryStopped;
use Modules\Workspace\Models\Task;
use Modules\Workspace\Models\TaskTimeEntry;
use Modules\Workspace\Http\Resources\TaskTimeEntryResource;
use Modules\Workspace\Services\TimeTrackingService;

class TimeTrackingController extends Controller
{
    public function __construct(
        private readonly TimeTrackingService $timeTracking
    ) {}

    public function dailyTotal(int $userId): JsonResponse
    {
        return response()->json([
            'user_id' => $userId,
            'date' => today()->toDateString(),
            'total_seconds' => $this->timeTracking->todayTotalSeconds($userId),
        ]);
    }

    public function active(): JsonResponse
    {
        $entry = TaskTimeEntry::where('user_id', Auth::id())
            ->whereNull('stopped_at')
            ->latest('started_at')
            ->first();

        return response()->json([
            'data' => $entry ? new TaskTimeEntryResource($entry) : null,
        ]);
    }

    public function start(Request $request, $taskId)
    {
        $task = Task::find($taskId);
        if (!$task) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Task not found.'], 404);
        }

        $activeTimer = TaskTimeEntry::where('user_id', Auth::id())
            ->whereNull('stopped_at')
            ->first();

        if ($activeTimer) {
            return response()->json([
                'code' => 'BAD_REQUEST',
                'message' => 'A timer is already running.',
                'active_entry' => new TaskTimeEntryResource($activeTimer),
            ], 409);
        }

        $entry = TaskTimeEntry::create([
            'task_id' => $taskId,
            'user_id' => Auth::id(),
            'started_at' => now(),
            'duration_seconds' => 0,
        ]);

        event(new TimeEntryStarted($entry));

        AuditLog::record(
            module: 'workspace',
            action: 'time_start',
            entity: $entry,
            label: 'Task: '.$task->title,
            changes: ['new' => ['task_id' => (int) $taskId, 'started_at' => $entry->started_at->toIso8601String()]],
        );

        return (new TaskTimeEntryResource($entry))->response()->setStatusCode(201);
    }

    public function stop(Request $request, $taskId, $entryId)
    {
        $entry = TaskTimeEntry::where('task_id', $taskId)->find($entryId);

        if (!$entry) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Time entry not found.'], 404);
        }

        if ($entry->stopped_at) {
            return response()->json(['code' => 'BAD_REQUEST', 'message' => 'This timer has already been stopped.'], 400);
        }

        $stoppedAt = now();
        $durationSeconds = $entry->started_at->diffInSeconds($stoppedAt, absolute: true);

        $entry->update([
            'stopped_at' => $stoppedAt,
            'duration_seconds' => $durationSeconds,
        ]);

        event(new TimeEntryStopped($entry));

        $task = Task::find($taskId);
        AuditLog::record(
            module: 'workspace',
            action: 'time_stop',
            entity: $entry,
            label: $task ? 'Task: '.$task->title : 'Task #'.$taskId,
            changes: [
                'new' => [
                    'duration_seconds' => $durationSeconds,
                    'stopped_at' => $stoppedAt->toIso8601String(),
                ],
            ],
        );

        return new TaskTimeEntryResource($entry);
    }

    public function index($taskId)
    {
        $task = Task::find($taskId);
        if (!$task) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Task not found.'], 404);
        }

        return TaskTimeEntryResource::collection($task->timeEntries);
    }
}
