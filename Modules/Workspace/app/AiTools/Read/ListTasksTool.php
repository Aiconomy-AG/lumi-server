<?php

namespace Modules\Workspace\AiTools\Read;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Workspace\AiTools\AbstractAiTool;
use Modules\Workspace\Models\Task;

class ListTasksTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'list_tasks';
    }

    public function description(): string
    {
        return 'List workspace tasks with optional filters by project or status. Returns up to 30 tasks.';
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
                'project_id' => ['type' => 'integer', 'description' => 'Filter by project ID'],
                'status' => [
                    'type' => 'string',
                    'enum' => $this->taskStatusEnum(),
                    'description' => 'Filter by task status',
                ],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'project_id' => ['sometimes', 'integer', 'exists:projects,id'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', $this->taskStatusEnum())],
        ];
    }

    public function authorize(User $user, array $arguments): bool
    {
        return Gate::forUser($user)->allows('viewAny', Task::class);
    }

    public function execute(User $user, array $arguments): array
    {
        $validated = $this->validate($arguments);

        $query = Task::query()->with('assignees:id,name');

        if (isset($validated['project_id'])) {
            $query->where('project_id', $validated['project_id']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $tasks = $query->orderBy('due_date')->limit(30)->get();

        return [
            'tasks' => $tasks->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'due_date' => $task->due_date?->toDateString(),
                'project_id' => $task->project_id,
                'parent_id' => $task->parent_id,
                'assignee_names' => $task->assignees->pluck('name')->all(),
            ])->all(),
        ];
    }

    public function summarize(array $arguments): string
    {
        return 'List tasks';
    }
}
