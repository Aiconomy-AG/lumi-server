<?php

namespace Modules\Workspace\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Workspace\Models\Project;

class ProjectService
{
    public function getAll(): Collection
    {
        return Project::query()
            ->orderBy('deadline')
            ->get();
    }

    public function create(array $data): Project
    {
        return Project::query()->create($data);
    }

    public function getById(int $projectId): Project
    {
        return Project::query()->findOrFail($projectId);
    }

    public function update(
        Project $project,
        array $data
    ): Project {
        $project->update($data);

        return $project->refresh();
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }
}
