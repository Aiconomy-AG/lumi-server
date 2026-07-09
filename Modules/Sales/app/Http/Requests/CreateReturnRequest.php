<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],

            'shop_domain' => ['required_without:order_id', 'nullable', 'string', 'max:255'],
            'shopify_customer_id' => ['nullable', 'string', 'max:255'],
            'shopify_order_id' => ['nullable', 'string', 'max:255'],
            'shopify_order_name' => ['nullable', 'string', 'max:255'],

            'email' => ['required_without:customer_id', 'nullable', 'email', 'max:255'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],

            'items.*.order_item_id' => ['nullable', 'integer', 'exists:order_items,id'],

            'items.*.shopify_line_item_id' => ['nullable', 'string', 'max:255'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],

            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
