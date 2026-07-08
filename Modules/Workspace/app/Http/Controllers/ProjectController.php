<?php

namespace Modules\Workspace\Http\Controllers;

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

        $project = $this->projectService->update(
            $project,
            $request->validated()
        );

        return new ProjectResource($project);
    }

    public function destroy(int $projectId): JsonResponse
    {
        $project = $this->projectService->getById($projectId);

        if (!$project) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Project not found.'], 404);
        }

        $this->projectService->delete($project);

        return response()->json([
            'message' => 'Project deleted successfully.',
        ]);
    }
}
