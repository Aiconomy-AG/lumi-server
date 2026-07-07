<?php

namespace Modules\Sales\Transformers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class IngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_allergen' => $this->is_allergen,
            'is_vegan' => $this->is_vegan,
            'is_natural' => $this->is_natural,
        ];
    }
}
