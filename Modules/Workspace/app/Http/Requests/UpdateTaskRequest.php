<?php

namespace Modules\Workspace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'sometimes',
                'required',
                'string',
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    'complete',
                    'to_do',
                    'in_progress',
                    'blocked',
                ]),
            ],
            'due_date' => [
                'sometimes',
                'required',
                'date',
            ],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:tasks,id',
            ],
            'project_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:projects,id',
            ],
        ];
    }
}
