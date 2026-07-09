<?php

namespace Modules\Workspace\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Modules\Workspace\Models\Task;

class TaskService
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

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

    public function getById(int $taskId): ?Task
    {
        return Task::query()
            ->with([
                'assignees',
                'subtasks',
            ])
            ->find($taskId);
    }

    public function update(
        Task $task,
        array $data,
        ?int $actorUserId = null
    ): Task {
        $watchedFields = [
            'title' => 'task_title_changed',
            'description' => 'task_description_changed',
            'status' => 'task_status_changed',
            'due_date' => 'task_due_date_changed',
            'project_id' => 'task_project_changed',
        ];

        $changes = [];

        foreach ($watchedFields as $field => $type) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $oldValue = $this->normalizeNotificationValue($task->getAttribute($field));
            $newValue = $this->normalizeNotificationValue($data[$field]);

            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'type' => $type,
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        $task->update($data);

        $task = $task->refresh()->load([
            'assignees',
            'subtasks',
        ]);

        foreach ($changes as $field => $change) {
            $this->notificationService->createForRecipients(
                type: $change['type'],
                source: 'task',
                recipientUserIds: $this->recipientIdsForTask($task, $actorUserId),
                actorUserId: $actorUserId,
                taskId: $task->id,
                payload: [
                    'field' => $field,
                    'old_value' => $change['old'],
                    'new_value' => $change['new'],
                    'task_title' => $task->title,
                ],
            );
        }

        return $task;
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function assignEmployees(
        Task $task,
        array $employeeIds,
        ?int $actorUserId = null
    ): Task {
        $existingEmployeeIds = $task->assignees()->pluck('users.id')->all();

        $task->assignees()->syncWithoutDetaching($employeeIds);

        $task = $task->refresh()->load([
            'assignees',
            'subtasks',
        ]);

        $newEmployeeIds = array_values(array_diff($employeeIds, $existingEmployeeIds));

        $this->notificationService->createForRecipients(
            type: 'task_assigned',
            source: 'task',
            recipientUserIds: $this->excludeActor($newEmployeeIds, $actorUserId),
            actorUserId: $actorUserId,
            taskId: $task->id,
            payload: [
                'task_title' => $task->title,
            ],
        );

        return $task;
    }

    public function removeEmployee(
        Task $task,
        int $employeeId,
        ?int $actorUserId = null
    ): Task {
        $task->assignees()->detach($employeeId);

        $task = $task->refresh()->load([
            'assignees',
            'subtasks',
        ]);

        $this->notificationService->createForRecipients(
            type: 'task_unassigned',
            source: 'task',
            recipientUserIds: $this->excludeActor([$employeeId], $actorUserId),
            actorUserId: $actorUserId,
            taskId: $task->id,
            payload: [
                'task_title' => $task->title,
            ],
        );

        return $task;
    }

    private function recipientIdsForTask(Task $task, ?int $actorUserId): array
    {
        return $this->excludeActor(
            $task->assignees->pluck('id')->all(),
            $actorUserId
        );
    }

    private function excludeActor(array $userIds, ?int $actorUserId): array
    {
        return collect($userIds)
            ->filter(fn (int $userId) => $actorUserId === null || $userId !== $actorUserId)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeNotificationValue(mixed $value): mixed
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return $value;
    }
}
