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
            'price' => $this->price,
            'name' => $this->name,
            'image_url' => $this->image_url,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'variants' => ProductVariantResource::collection(
                $this->whenLoaded('variants')
            ),
            'ingredients' => IngredientResource::collection(
                $this->whenLoaded('ingredients')
            ),
        ];
    }
}
