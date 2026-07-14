<?php

namespace Modules\Workspace\AiTools\Write;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Workspace\AiTools\AbstractAiTool;
use Modules\Workspace\Models\Task;
use Modules\Workspace\Services\TaskService;

class CreateTaskTool extends AbstractAiTool
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function name(): string
    {
        return 'create_task';
    }

    public function description(): string
    {
        return 'Create a new task or subtask. Requires title, description, status, due_date, and project_id.';
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
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => $this->taskStatusEnum()],
                'due_date' => ['type' => 'string', 'description' => 'ISO date YYYY-MM-DD'],
                'project_id' => ['type' => 'integer'],
                'parent_id' => ['type' => 'integer', 'description' => 'Parent task ID for subtasks'],
                'employee_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'User IDs to assign',
                ],
            ],
            'required' => ['title', 'description', 'status', 'due_date', 'project_id'],
        ];
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'status' => ['required', Rule::in($this->taskStatusEnum())],
            'due_date' => ['required', 'date'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'parent_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'employee_ids' => ['sometimes', 'array'],
            'employee_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ];
    }

    public function authorize(User $user, array $arguments): bool
    {
        return Gate::forUser($user)->allows('create', Task::class);
    }

    public function execute(User $user, array $arguments): array
    {
        $validated = $this->validate($arguments);
        $task = $this->taskService->create($validated, $user->id);

        return [
            'task_id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
        ];
    }

    public function summarize(array $arguments): string
    {
        $title = $arguments['title'] ?? 'Untitled';

        return "Create task: {$title}";
    }
}
