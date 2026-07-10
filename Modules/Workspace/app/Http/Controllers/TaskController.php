<?php

namespace Modules\Workspace\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Workspace\Http\Requests\AssignTaskEmployeesRequest;
use Modules\Workspace\Http\Requests\StoreTaskRequest;
use Modules\Workspace\Http\Requests\UpdateTaskRequest;
use Modules\Workspace\Services\TaskService;
use Modules\Workspace\Transformers\TaskResource;

class TaskController
{
    public function __construct(
        private readonly TaskService $taskService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $tasks = $this->taskService->getAll();

        return TaskResource::collection($tasks);
    }

    public function store(
        StoreTaskRequest $request
    ): TaskResource|JsonResponse {
        $task = $this->taskService->create(
            $request->validated(),
            (int) $request->user()->id
        );

        AuditLog::record(
            module: 'workspace',
            action: 'task_create',
            entity: $task,
            label: 'Task: '.$task->title,
            changes: ['new' => ['title' => $task->title, 'status' => $task->status]],
        );

        return new TaskResource($task);
    }

    public function show(int $taskId): TaskResource|JsonResponse
    {
        $task = $this->taskService->getById($taskId);

        if (! $task) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Task not found.'], 404);
        }

        return new TaskResource($task);
    }

    public function update(
        UpdateTaskRequest $request,
        int $taskId
    ): TaskResource|JsonResponse {
        $task = $this->taskService->getById($taskId);

        if (! $task) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Task not found.'], 404);
        }

        $validated = $request->validated();
        $oldValues = [];
        $newValues = [];
        foreach ($validated as $key => $value) {
            if ($key === 'employee_ids') {
                continue;
            }
            $original = $task->getAttribute($key);
            if ($original != $value) {
                $oldValues[$key] = $original instanceof \Illuminate\Support\Carbon
                    ? $original->toDateString()
                    : $original;
                $newValues[$key] = $value;
            }
        }

        $task = $this->taskService->update(
            $task,
            $validated,
            (int) $request->user()->id
        );

        if ($newValues !== []) {
            AuditLog::record(
                module: 'workspace',
                action: 'task_update',
                entity: $task,
                label: 'Task: '.$task->title,
                changes: ['old' => $oldValues, 'new' => $newValues],
            );
        }

        return new TaskResource($task);
    }

    public function destroy(int $taskId): JsonResponse
    {
        $task = $this->taskService->getById($taskId);

        if (! $task) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Task not found.'], 404);
        }

        $taskLabel = 'Task: '.$task->title;

        $this->taskService->delete($task);

        AuditLog::record(
            module: 'workspace',
            action: 'task_delete',
            entity: $task,
            label: $taskLabel,
            description: 'Task deleted.',
        );

        return response()->json([
            'message' => 'Task deleted successfully.',
        ]);
    }

    public function assignEmployees(
        AssignTaskEmployeesRequest $request,
        int $taskId
    ): TaskResource|JsonResponse {
        $task = $this->taskService->getById($taskId);

        if (! $task) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Task not found.'], 404);
        }

        $existingEmployeeIds = $task->assignees()->pluck('users.id')->all();
        $employeeIds = $request->validated('employee_ids');

        $task = $this->taskService->assignEmployees(
            $task,
            $employeeIds,
            (int) $request->user()->id
        );

        $newEmployeeIds = array_values(array_diff($employeeIds, $existingEmployeeIds));
        if ($newEmployeeIds !== []) {
            AuditLog::record(
                module: 'workspace',
                action: 'task_assign',
                entity: $task,
                label: 'Task: '.$task->title,
                changes: ['new' => ['employee_ids' => $newEmployeeIds]],
            );
        }

        return new TaskResource($task);
    }

    public function removeEmployee(
        Request $request,
        int $taskId,
        int $employeeId
    ): TaskResource|JsonResponse {
        $task = $this->taskService->getById($taskId);

        if (! $task) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Task not found.'], 404);
        }

        $task = $this->taskService->removeEmployee(
            $task,
            $employeeId,
            (int) $request->user()->id
        );

        AuditLog::record(
            module: 'workspace',
            action: 'task_unassign',
            entity: $task,
            label: 'Task: '.$task->title,
            changes: ['old' => ['employee_id' => $employeeId]],
        );

        return new TaskResource($task);
    }
}
