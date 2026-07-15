<?php

namespace Modules\Workspace\AiTools\Write;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Workspace\AiTools\AbstractAiTool;
use Modules\Workspace\Models\Task;
use Modules\Workspace\Services\TaskService;

class UpdateTaskTool extends AbstractAiTool
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function name(): string
    {
        return 'update_task';
    }

    public function description(): string
    {
        return 'Update an existing task fields: title, description, status, due_date, project_id, or parent_id.';
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
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => $this->taskStatusEnum()],
                'due_date' => ['type' => 'string', 'description' => 'ISO date YYYY-MM-DD'],
                'project_id' => ['type' => 'integer'],
                'parent_id' => ['type' => 'integer', 'description' => 'Parent task ID for subtasks'],
            ],
            'required' => ['task_id'],
        ];
    }

    public function rules(): array
    {
        return [
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'status' => ['sometimes', 'required', Rule::in($this->taskStatusEnum())],
            'due_date' => ['sometimes', 'required', 'date'],
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:tasks,id'],
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
        $taskId = $validated['task_id'];
        unset($validated['task_id']);

        $task = Task::query()->findOrFail($taskId);
        $task = $this->taskService->update($task, $validated, $user->id);

        return [
            'task_id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
        ];
    }

    public function summarize(array $arguments): string
    {
        $taskId = $arguments['task_id'] ?? '?';
        $changes = collect($arguments)
            ->except('task_id')
            ->keys()
            ->implode(', ');

        return "Update task #{$taskId}".($changes ? " ({$changes})" : '');
    }
}
