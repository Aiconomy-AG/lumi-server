<?php

namespace Modules\Workspace\AiTools\Read;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Workspace\AiTools\AbstractAiTool;
use Modules\Workspace\Models\Task;

class GetTaskTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'get_task';
    }

    public function description(): string
    {
        return 'Get detailed information about a single task by ID, including subtasks.';
    }

    public function isWrite(): bool
    {
        return false;
    }

    protected function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer', 'description' => 'The task ID'],
            ],
            'required' => ['task_id'],
        ];
    }

    public function rules(): array
    {
        return [
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
        ];
    }

    public function authorize(User $user, array $arguments): bool
    {
        $task = Task::query()->find($arguments['task_id'] ?? 0);

        return $task && Gate::forUser($user)->allows('view', $task);
    }

    public function execute(User $user, array $arguments): array
    {
        $validated = $this->validate($arguments);

        $task = Task::query()
            ->with(['assignees:id,name', 'subtasks', 'project:id,name'])
            ->findOrFail($validated['task_id']);

        return [
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'due_date' => $task->due_date?->toDateString(),
                'project_id' => $task->project_id,
                'project_name' => $task->project?->name,
                'parent_id' => $task->parent_id,
                'assignees' => $task->assignees->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                ])->all(),
                'subtasks' => $task->subtasks->map(fn (Task $s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'status' => $s->status,
                ])->all(),
            ],
        ];
    }

    public function summarize(array $arguments): string
    {
        return 'View task #'.($arguments['task_id'] ?? '?');
    }
}
