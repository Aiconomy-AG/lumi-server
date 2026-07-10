<?php

namespace Modules\Workspace\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Workspace\Http\Requests\StoreProjectRequest;
use Modules\Workspace\Http\Requests\UpdateProjectRequest;
use Modules\Workspace\Services\ProjectService;
use Modules\Workspace\Transformers\ProjectResource;

class ProjectController
{
    public function __construct(
        private readonly ProjectService $projectService
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        $projects = $this->projectService->getAll();

        return ProjectResource::collection($projects);
    }

    public function store(
        StoreProjectRequest $request
    ): ProjectResource|\Illuminate\Http\JsonResponse {
        $project = $this->projectService->create(
            $request->validated()
        );

        AuditLog::record(
            module: 'workspace',
            action: 'project_create',
            entity: $project,
            label: 'Project: '.$project->name,
            changes: ['new' => ['name' => $project->name, 'status' => $project->status]],
        );

        return new ProjectResource($project);
    }

    public function show(int $projectId): ProjectResource|\Illuminate\Http\JsonResponse
    {
        $project = $this->projectService->getById($projectId);

        if (!$project) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Project not found.'], 404);
        }

        return new ProjectResource($project);
    }

    public function update(
        UpdateProjectRequest $request,
        int $projectId
    ): ProjectResource|\Illuminate\Http\JsonResponse {
        $project = $this->projectService->getById($projectId);

        if (!$project) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Project not found.'], 404);
        }

        $validated = $request->validated();
        $oldValues = [];
        $newValues = [];
        foreach ($validated as $key => $value) {
            $original = $project->getAttribute($key);
            if ($original != $value) {
                $oldValues[$key] = $original;
                $newValues[$key] = $value;
            }
        }

        $project = $this->projectService->update(
            $project,
            $validated
        );

        if ($newValues !== []) {
            AuditLog::record(
                module: 'workspace',
                action: 'project_update',
                entity: $project,
                label: 'Project: '.$project->name,
                changes: ['old' => $oldValues, 'new' => $newValues],
            );
        }

        return new ProjectResource($project);
    }

    public function destroy(int $projectId): JsonResponse
    {
        $project = $this->projectService->getById($projectId);

        if (!$project) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Project not found.'], 404);
        }

        $projectLabel = 'Project: '.$project->name;

        $this->projectService->delete($project);

        AuditLog::record(
            module: 'workspace',
            action: 'project_delete',
            entity: $project,
            label: $projectLabel,
            description: 'Project deleted.',
        );

        return response()->json([
            'message' => 'Project deleted successfully.',
        ]);
    }
}
