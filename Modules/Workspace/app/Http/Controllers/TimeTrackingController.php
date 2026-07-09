<?php
namespace Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\Workspace\Models\Task;
use Modules\Workspace\Models\TaskTimeEntry;
use Modules\Workspace\Http\Resources\TaskTimeEntryResource;
use Modules\Workspace\Services\TimeTrackingService;

class TimeTrackingController extends Controller
{
    public function __construct(
        private readonly TimeTrackingService $timeTracking
    ) {}

    public function dailyTotal(Request $request, int $userId): JsonResponse
    {
        $dateInput = $request->query('date');

        try {
            $day = $dateInput !== null
                ? Carbon::parse((string) $dateInput)->toDateString()
                : Carbon::now()->toDateString();
        } catch (\Throwable) {
            return response()->json([
                'code' => 'BAD_REQUEST',
                'message' => 'Invalid date. Expected format YYYY-MM-DD.',
            ], 422);
        }

        $totalSeconds = $this->timeTracking->dailyTotalSeconds($userId, $day);

        return response()->json([
            'user_id' => $userId,
            'date' => $day,
            'total_seconds' => $totalSeconds,
        ]);
    }

    public function start(Request $request, $taskId)
    {
        $task = Task::find($taskId);
        if (!$task) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Task not found.'], 404);
        }

        $activeTimer = TaskTimeEntry::where('task_id', $taskId)
            ->where('user_id', Auth::id())
            ->whereNull('stopped_at')
            ->first();

        if ($activeTimer) {
            return response()->json(['code' => 'BAD_REQUEST', 'message' => 'A timer is already running for this task.'], 400);
        }

        $entry = TaskTimeEntry::create([
            'task_id' => $taskId,
            'user_id' => Auth::id(),
            'started_at' => now(),
            'duration_seconds' => 0,
        ]);

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
