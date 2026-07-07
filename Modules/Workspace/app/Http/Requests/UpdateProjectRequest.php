<?php

namespace Modules\Workspace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'deadline' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    'complete',
                    'in_progress',
                    'to_do',
                    'blocked',
                ]),
            ],
        ];
    }
}
