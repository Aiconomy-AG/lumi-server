<?php

namespace Modules\Sales\Transformers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'price' => $this->price,
            'name' => $this->name,
            'image_url' => $this->image_url,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category_name' => $this->relationLoaded('category') ? $this->category?->name : null,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'variants' => ProductVariantResource::collection(
                $this->whenLoaded('variants')
            ),
            'ingredients' => IngredientResource::collection(
                $this->whenLoaded('ingredients')
            ),
        ];
    }
}
