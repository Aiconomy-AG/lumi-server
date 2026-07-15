<?php

namespace Modules\Workspace\AiTools\Write;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Workspace\AiTools\AbstractAiTool;
use Modules\Workspace\Models\Task;
use Modules\Workspace\Services\TaskService;

class DeleteTaskTool extends AbstractAiTool
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function name(): string
    {
        return 'delete_task';
    }

    public function description(): string
    {
        return 'Soft-delete a task and its subtasks by ID.';
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

        return $task && Gate::forUser($user)->allows('delete', $task);
    }

    public function execute(User $user, array $arguments): array
    {
        $validated = $this->validate($arguments);
        $task = Task::query()->findOrFail($validated['task_id']);
        $title = $task->title;

        $this->taskService->delete($task);

        return [
            'task_id' => $validated['task_id'],
            'title' => $title,
            'deleted' => true,
        ];
    }

    public function summarize(array $arguments): string
    {
        return 'Delete task #'.($arguments['task_id'] ?? '?');
    }
}
