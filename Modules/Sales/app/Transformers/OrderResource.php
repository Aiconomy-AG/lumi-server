<?php

namespace Modules\Sales\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'shopify_order_id' => $this->shopify_order_id,
            'shopify_order_name' => $this->shopify_order_name,
            'status' => $this->mapStatus($this->status, $this->payment_status),
            'subtotal' => (float) $this->subtotal,
            'shipping_cost' => (float) $this->shipping_cost,
            'total_amount' => (float) $this->total_amount,
            'shipping_address' => $this->shipping_address,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->mapPaymentStatus($this->status),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'return_requests' => ReturnRequestResource::collection($this->whenLoaded('returnRequests')),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Map database enum values to OpenAPI status enum:
     * [pending, processing, shipped, delivered, cancelled]
     */
    protected function mapStatus(string $dbStatus, string $dbPaymentStatus): string
    {
        if ($dbPaymentStatus === 'shipped') {
            return 'shipped';
        }
        if ($dbPaymentStatus === 'fulfilled') {
            return 'delivered';
        }
        if (in_array($dbStatus, ['expired', 'voided'])) {
            return 'cancelled';
        }
        if (in_array($dbStatus, ['paid', 'partially_paid', 'authorized'])) {
            return 'processing';
        }
        return 'pending';
    }

    /**
     * Map database status enum values to OpenAPI payment_status enum:
     * [pending, successful, failed]
     */
    protected function mapPaymentStatus(string $dbStatus): string
    {
        if ($dbStatus === 'paid') {
            return 'successful';
        }
        if (in_array($dbStatus, ['expired', 'voided', 'refunded'])) {
            return 'failed';
        }
        return 'pending';
    }
}
