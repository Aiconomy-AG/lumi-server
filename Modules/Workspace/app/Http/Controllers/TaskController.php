<?php

namespace Modules\Workspace\Http\Controllers;

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
            $request->validated()
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

        $task = $this->taskService->update(
            $task,
            $request->validated(),
            (int) $request->user()->id
        );

        return new TaskResource($task);
    }

    public function destroy(int $taskId): JsonResponse
    {
        $task = $this->taskService->getById($taskId);

        if (! $task) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Task not found.'], 404);
        }

        $this->taskService->delete($task);

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

        $task = $this->taskService->assignEmployees(
            $task,
            $request->validated('employee_ids'),
            (int) $request->user()->id
        );

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

        return new TaskResource($task);
    }
}
