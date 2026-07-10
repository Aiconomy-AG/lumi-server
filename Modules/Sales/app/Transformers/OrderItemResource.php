<?php

namespace Modules\Sales\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'variant' => new ProductVariantResource($this->whenLoaded('variant')),
        ];
    }
}
