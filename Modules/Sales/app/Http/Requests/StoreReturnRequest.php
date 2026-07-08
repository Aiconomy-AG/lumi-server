<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'max:255',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.order_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('order_items', 'id'),
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A return reason is required.',
            'items.required' => 'At least one order item must be selected.',
            'items.array' => 'The items field must be a valid list.',
            'items.min' => 'At least one order item must be selected.',

            'items.*.order_item_id.required' => 'Each returned item must have an order item ID.',
            'items.*.order_item_id.exists' => 'One of the selected order items does not exist.',
            'items.*.order_item_id.distinct' => 'The same order item cannot be added twice.',

            'items.*.quantity.required' => 'A return quantity is required for each item.',
            'items.*.quantity.integer' => 'The return quantity must be a whole number.',
            'items.*.quantity.min' => 'The return quantity must be at least 1.',
        ];
    }
}
