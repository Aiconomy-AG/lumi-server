<?php

namespace Modules\Workspace\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'shop_domain' => $this->shop_domain,
            'shopify_customer_id' => $this->shopify_customer_id,
            'shopify_order_id' => $this->shopify_order_id,
            'shopify_order_name' => $this->shopify_order_name,
            'email' => $this->email,
            'items' => $this->items,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'status' => $this->status,
            'refund_amount' => $this->refund_amount,
            'received_at' => $this->received_at?->toISOString(),
            'refunded_at' => $this->refunded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'return_items' => ReturnItemResource::collection(
                $this->whenLoaded('returnItems')
            ),
        ];
    }
}
