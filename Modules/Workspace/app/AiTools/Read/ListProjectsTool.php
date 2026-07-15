<?php

namespace Modules\Workspace\AiTools\Read;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Workspace\AiTools\AbstractAiTool;
use Modules\Workspace\Models\Project;

class ListProjectsTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'list_projects';
    }

    public function description(): string
    {
        return 'List all workspace projects. Returns up to 30 projects.';
    }

    public function isWrite(): bool
    {
        return false;
    }

    protected function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
        ];
    }

    public function rules(): array
    {
        return [];
    }

    public function authorize(User $user, array $arguments): bool
    {
        return Gate::forUser($user)->allows('viewAny', Project::class);
    }

    public function execute(User $user, array $arguments): array
    {
        $projects = Project::query()
            ->orderBy('deadline')
            ->limit(30)
            ->get(['id', 'name', 'status', 'deadline']);

        return [
            'projects' => $projects->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status,
                'deadline' => $p->deadline?->toDateString(),
            ])->all(),
        ];
    }

    public function summarize(array $arguments): string
    {
        return 'List projects';
    }
}
