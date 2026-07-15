<?php

namespace Modules\Workspace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'message' => ['required_without:image', 'nullable', 'string', 'max:5000'],
            'image' => [
                'required_without:message',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp,gif',
                'max:'.config('media.image_max_kb'),
                'dimensions:max_width=8000,max_height=8000',
            ],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
