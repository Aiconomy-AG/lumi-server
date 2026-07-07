<?php

namespace Modules\Workspace\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'due_date' => $this->due_date,
            'parent_id' => $this->parent_id,
            'project_id' => $this->project_id,

            'assignees' => $this->whenLoaded(
                'assignees',
                fn () => $this->assignees->map(fn ($employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                ])
            ),

            'subtasks' => TaskResource::collection(
                $this->whenLoaded('subtasks')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
