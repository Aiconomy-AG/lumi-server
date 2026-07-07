<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddWishlistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
        ];
    }
}
