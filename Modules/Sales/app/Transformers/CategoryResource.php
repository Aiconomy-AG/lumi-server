<?php

namespace Modules\Sales\Transformers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id ? (int) $this->parent_id : null,
            'name' => $this->name,
        ];
    }
}
