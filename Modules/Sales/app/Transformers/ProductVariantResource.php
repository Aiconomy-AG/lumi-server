<?php

namespace Modules\Sales\Transformers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'price' => $this->price,
            'weight' => $this->weight,
            'weight_unit' => $this->weight_unit,
            'stock_quantity' => $this->stock_quantity,
            "colour" =>$this->colour,
        ];
    }
}
