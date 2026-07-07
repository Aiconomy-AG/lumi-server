<?php

namespace Modules\Workspace\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Workspace\Models\Task;

class TaskService
{
    public function getAll(): Collection
    {
        return Task::query()
            ->with([
                'assignees',
                'subtasks',
            ])
            ->orderBy('due_date')
            ->get();
    }

    public function create(array $data): Task
    {
        $task = Task::query()->create($data);

        return $task->load([
            'assignees',
            'subtasks',
        ]);
    }

    public function getById(int $taskId): Task
    {
        return Task::query()
            ->with([
                'assignees',
                'subtasks',
            ])
            ->findOrFail($taskId);
    }

    public function update(
        Task $task,
        array $data
    ): Task {
        $task->update($data);

        return $task->refresh()->load([
            'assignees',
            'subtasks',
        ]);
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function assignEmployees(
        Task $task,
        array $employeeIds
    ): Task {
        $task->assignees()->syncWithoutDetaching($employeeIds);

        return $task->refresh()->load([
            'assignees',
            'subtasks',
        ]);
    }

    public function removeEmployee(
        Task $task,
        int $employeeId
    ): Task {
        $task->assignees()->detach($employeeId);

        return $task->refresh()->load([
            'assignees',
            'subtasks',
        ]);
    }
}
