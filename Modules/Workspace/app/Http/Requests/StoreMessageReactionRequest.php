<?php

namespace Modules\Workspace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', 'min:1', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('emoji') && is_string($this->input('emoji'))) {
            $this->merge([
                'emoji' => trim($this->input('emoji')),
            ]);
        }
    }
}
