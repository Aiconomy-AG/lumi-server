<?php

namespace Modules\Workspace\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_request_id' => $this->return_request_id,
            'order_item_id' => $this->order_item_id,
            'quantity' => $this->quantity,
            'order_item' => $this->whenLoaded('orderItem'),
        ];
    }
}
