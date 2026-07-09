<?php

namespace Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Sales\Models\ReturnItem;

/**
 * @mixin ReturnItem
 */
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
