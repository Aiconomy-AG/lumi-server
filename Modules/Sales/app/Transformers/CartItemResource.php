<?php

namespace Modules\Sales\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cart_id' => $this->cart_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,

            'variant' => new ProductVariantResource(
                $this->whenLoaded('variant')
            ),
        ];
    }
}
