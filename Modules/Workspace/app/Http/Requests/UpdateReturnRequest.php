<?php

namespace Modules\Workspace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                'requested',
                'approved',
                'rejected',
                'received',
                'refunded',
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
