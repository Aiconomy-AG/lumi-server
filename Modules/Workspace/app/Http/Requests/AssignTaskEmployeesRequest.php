<?php

namespace Modules\Workspace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignTaskEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'employee_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:users,id',
            ],
        ];
    }
}
