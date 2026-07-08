<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Sales\Models\Order;
use Modules\Sales\Models\OrderItem;
use Modules\Sales\Models\ReturnItem;
use Modules\Sales\Models\ReturnRequest;

class ReturnService
{
    public function createReturn(
        int $customerId,
        int $orderId,
        string $reason,
        array $items
    ): ReturnRequest {
        return DB::transaction(function () use (
            $customerId,
            $orderId,
            $reason,
            $items
        ): ReturnRequest {
            $order = Order::query()
                ->where('id', $orderId)
                ->where('customer_id', $customerId)
                ->firstOrFail();

            $returnRequest = ReturnRequest::create([
                'order_id' => $order->id,
                'customer_id' => $customerId,
                'status' => 'requested',
                'reason' => $reason,
                'refund_amount' => 0,
            ]);

            $totalRefundAmount = 0;

            foreach ($items as $itemData) {
                $orderItem = OrderItem::query()
                    ->where('id', $itemData['order_item_id'])
                    ->where('order_id', $order->id)
                    ->first();

                if (! $orderItem) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the selected items does not belong to this order.',
                    ]);
                }

                $alreadyReturnedQuantity = ReturnItem::query()
                    ->where('order_item_id', $orderItem->id)
                    ->whereHas('returnRequest', function ($query): void {
                        $query->whereNotIn('status', [
                            'rejected',
                        ]);
                    })
                    ->sum('quantity');

                $remainingQuantity =
                    $orderItem->quantity - $alreadyReturnedQuantity;

                if ($itemData['quantity'] > $remainingQuantity) {
                    throw ValidationException::withMessages([
                        'items' => "The return quantity for order item {$orderItem->id} exceeds the remaining returnable quantity.",
                    ]);
                }

                ReturnItem::create([
                    'return_request_id' => $returnRequest->id,
                    'order_item_id' => $orderItem->id,
                    'quantity' => $itemData['quantity'],
                ]);

                $unitPrice = $orderItem->unit_price
                    ?? $orderItem->price
                    ?? 0;

                $totalRefundAmount +=
                    $unitPrice * $itemData['quantity'];
            }

            $returnRequest->update([
                'refund_amount' => $totalRefundAmount,
            ]);

            return $returnRequest->load([
                'order',
                'customer',
                'items.orderItem.product',
            ]);
        });
    }
}
