<?php

namespace Modules\Workspace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['client_instance_id' => ['required', 'string', 'max:100']];
    }
}
