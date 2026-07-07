<?php
namespace Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Workspace\Models\Task;
use Modules\Workspace\Models\TaskTimeEntry;
use Modules\Workspace\Http\Resources\TaskTimeEntryResource;

class TimeTrackingController extends Controller
{
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
        $durationSeconds = $stoppedAt->diffInSeconds($entry->started_at);

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
