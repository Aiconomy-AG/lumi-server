<?php

namespace Modules\Workspace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'required',
                'string',
            ],
            'status' => [
                'required',
                Rule::in([
                    'complete',
                    'to_do',
                    'in_progress',
                    'blocked',
                ]),
            ],
            'due_date' => [
                'required',
                'date',
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:tasks,id',
            ],
            'project_id' => [
                'required',
                'integer',
                'exists:projects,id',
            ],
        ];
    }
}
