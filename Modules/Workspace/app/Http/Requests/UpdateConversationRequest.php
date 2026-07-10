<?php

namespace Modules\Workspace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'add_participants_employee_ids' => ['sometimes', 'array'],
            'add_participants_employee_ids.*' => ['integer', 'exists:users,id'],
            'remove_participants_employee_ids' => ['sometimes', 'array'],
            'remove_participants_employee_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
