<?php

namespace Modules\Workspace\AiTools\Write;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Workspace\AiTools\AbstractAiTool;
use Modules\Workspace\Models\Task;
use Modules\Workspace\Services\TaskService;

class AssignTaskEmployeesTool extends AbstractAiTool
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function name(): string
    {
        return 'assign_task_employees';
    }

    public function description(): string
    {
        return 'Assign employees to a task by user IDs.';
    }

    public function isWrite(): bool
    {
        return true;
    }

    protected function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer'],
                'employee_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'User IDs to assign',
                ],
            ],
            'required' => ['task_id', 'employee_ids'],
        ];
    }

    public function rules(): array
    {
        return [
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ];
    }

    public function authorize(User $user, array $arguments): bool
    {
        $task = Task::query()->find($arguments['task_id'] ?? 0);

        return $task && Gate::forUser($user)->allows('update', $task);
    }

    public function execute(User $user, array $arguments): array
    {
        $validated = $this->validate($arguments);
        $task = Task::query()->findOrFail($validated['task_id']);
        $task = $this->taskService->assignEmployees($task, $validated['employee_ids'], $user->id);

        return [
            'task_id' => $task->id,
            'title' => $task->title,
            'assignee_ids' => $task->assignees->pluck('id')->all(),
        ];
    }

    public function summarize(array $arguments): string
    {
        $count = count($arguments['employee_ids'] ?? []);

        return "Assign {$count} employee(s) to task #".($arguments['task_id'] ?? '?');
    }
}
