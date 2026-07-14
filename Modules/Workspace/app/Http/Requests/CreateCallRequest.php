<?php

namespace Modules\Workspace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Workspace\Domain\Calls\CallMode;
use Modules\Workspace\Domain\Calls\CallType;

class CreateCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'callee_ids' => ['required', 'array', 'min:1'],
            'callee_ids.*' => ['integer', 'exists:users,id'],
            'type' => ['sometimes', 'string', Rule::enum(CallType::class)],
            'mode' => ['sometimes', 'string', Rule::enum(CallMode::class)],
            'client_instance_id' => ['required', 'string', 'max:100'],
        ];
    }
}
