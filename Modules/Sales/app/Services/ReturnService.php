<?php

namespace Modules\Sales\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Sales\Models\Order;
use Modules\Sales\Models\OrderItem;
use Modules\Sales\Models\ReturnItem;
use Modules\Sales\Models\ReturnRequest;

class ReturnService
{
    public function createReturnFromOrder(
        int $customerId,
        int $orderId,
        string $reason,
        array $items,
        ?string $notes = null
    ): ReturnRequest {
        return DB::transaction(function () use (
            $customerId,
            $orderId,
            $reason,
            $items,
            $notes
        ): ReturnRequest {
            $this->validateReason($reason);

            if (empty($items)) {
                throw ValidationException::withMessages([
                    'items' => 'You must select at least one item to return.',
                ]);
            }

            $order = Order::query()
                ->where('id', $orderId)
                ->where('customer_id', $customerId)
                ->firstOrFail();

            $normalizedItems = $this->normalizeItems($items);

            $returnRequest = ReturnRequest::query()->create([
                'order_id' => $order->id,
                'customer_id' => $customerId,
                'shop_domain' => $order->shop_domain ?? null,
                'shopify_customer_id' => $order->shopify_customer_id ?? null,
                'shopify_order_id' => $order->shopify_order_id ?? null,
                'shopify_order_name' => $order->shopify_order_name ?? null,
                'email' => $order->email ?? $order->customer?->email ?? null,
                'items' => $normalizedItems,
                'reason' => $reason,
                'notes' => $notes,
                'status' => ReturnRequest::STATUS_REQUESTED,
                'refund_amount' => 0,
            ]);

            $totalRefundAmount = 0;

            foreach ($normalizedItems as $itemData) {
                $orderItem = OrderItem::query()
                    ->where('id', $itemData['order_item_id'])
                    ->where('order_id', $order->id)
                    ->lockForUpdate()
                    ->first();

                if (! $orderItem) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the selected items does not belong to this order.',
                    ]);
                }

                $remainingQuantity = $this->getRemainingReturnableQuantity($orderItem);

                if ($itemData['quantity'] > $remainingQuantity) {
                    throw ValidationException::withMessages([
                        'items' => "The return quantity for order item {$orderItem->id} exceeds the remaining returnable quantity.",
                    ]);
                }

                ReturnItem::query()->create([
                    'return_request_id' => $returnRequest->id,
                    'order_item_id' => $orderItem->id,
                    'quantity' => $itemData['quantity'],
                ]);

                $unitPrice = $this->getOrderItemUnitPrice($orderItem);

                $totalRefundAmount += $unitPrice * $itemData['quantity'];
            }

            $returnRequest->update([
                'refund_amount' => $totalRefundAmount,
            ]);

            return $this->loadReturnRequestRelations($returnRequest);
        });
    }

    public function createShopifyReturn(
        string $shopDomain,
        string $email,
        string $reason,
        array $items,
        ?string $shopifyCustomerId = null,
        ?string $shopifyOrderId = null,
        ?string $shopifyOrderName = null,
        ?string $notes = null
    ): ReturnRequest {
        return DB::transaction(function () use (
            $shopDomain,
            $email,
            $reason,
            $items,
            $shopifyCustomerId,
            $shopifyOrderId,
            $shopifyOrderName,
            $notes
        ): ReturnRequest {
            $this->validateReason($reason);

            if (empty($items)) {
                throw ValidationException::withMessages([
                    'items' => 'You must select at least one item to return.',
                ]);
            }

            $normalizedItems = $this->normalizeShopifyItems($items);

            $returnRequest = ReturnRequest::query()->create([
                'shop_domain' => $shopDomain,
                'shopify_customer_id' => $shopifyCustomerId,
                'shopify_order_id' => $shopifyOrderId,
                'shopify_order_name' => $shopifyOrderName,
                'email' => $email,
                'items' => $normalizedItems,
                'reason' => $reason,
                'notes' => $notes,
                'status' => ReturnRequest::STATUS_REQUESTED,
                'refund_amount' => $this->calculateShopifyRefundAmount($normalizedItems),
            ]);

            return $this->loadReturnRequestRelations($returnRequest);
        });
    }

    public function approveReturn(int $returnRequestId): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequestId): ReturnRequest {
            $returnRequest = ReturnRequest::query()
                ->lockForUpdate()
                ->findOrFail($returnRequestId);

            if ($returnRequest->status !== ReturnRequest::STATUS_REQUESTED) {
                throw ValidationException::withMessages([
                    'status' => 'Only requested returns can be approved.',
                ]);
            }

            $returnRequest->update([
                'status' => ReturnRequest::STATUS_APPROVED,
            ]);

            return $this->loadReturnRequestRelations($returnRequest);
        });
    }

    public function rejectReturn(int $returnRequestId, ?string $notes = null): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequestId, $notes): ReturnRequest {
            $returnRequest = ReturnRequest::query()
                ->lockForUpdate()
                ->findOrFail($returnRequestId);

            if ($returnRequest->status !== ReturnRequest::STATUS_REQUESTED) {
                throw ValidationException::withMessages([
                    'status' => 'Only requested returns can be rejected.',
                ]);
            }

            $returnRequest->update([
                'status' => ReturnRequest::STATUS_REJECTED,
                'notes' => $notes ?? $returnRequest->notes,
            ]);

            return $this->loadReturnRequestRelations($returnRequest);
        });
    }

    public function markAsReceived(int $returnRequestId): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequestId): ReturnRequest {
            $returnRequest = ReturnRequest::query()
                ->lockForUpdate()
                ->findOrFail($returnRequestId);

            if ($returnRequest->status !== ReturnRequest::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'status' => 'Only approved returns can be marked as received.',
                ]);
            }

            $returnRequest->update([
                'status' => ReturnRequest::STATUS_RECEIVED,
                'received_at' => now(),
            ]);

            return $this->loadReturnRequestRelations($returnRequest);
        });
    }

    public function markAsRefunded(int $returnRequestId): ReturnRequest
    {
        return DB::transaction(function () use ($returnRequestId): ReturnRequest {
            $returnRequest = ReturnRequest::query()
                ->lockForUpdate()
                ->findOrFail($returnRequestId);

            if ($returnRequest->status !== ReturnRequest::STATUS_RECEIVED) {
                throw ValidationException::withMessages([
                    'status' => 'Only received returns can be marked as refunded.',
                ]);
            }

            $returnRequest->update([
                'status' => ReturnRequest::STATUS_REFUNDED,
                'refunded_at' => now(),
            ]);

            return $this->loadReturnRequestRelations($returnRequest);
        });
    }

    public function getReturn(int $returnRequestId): ReturnRequest
    {
        $returnRequest = ReturnRequest::query()->findOrFail($returnRequestId);

        return $this->loadReturnRequestRelations($returnRequest);
    }

    public function getReturnsForAdmin(?string $status = null): Collection
    {
        return ReturnRequest::query()
            ->when($status, function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->with([
                'order',
                'customer',
                'returnItems.orderItem.product',
            ])
            ->latest()
            ->get();
    }

    public function getReturnsForShop(string $shopDomain, ?string $status = null): Collection
    {
        return ReturnRequest::query()
            ->where('shop_domain', $shopDomain)
            ->when($status, function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->with([
                'order',
                'customer',
                'returnItems.orderItem.product',
            ])
            ->latest()
            ->get();
    }

    public function getReturnsForCustomer(int $customerId): Collection
    {
        return ReturnRequest::query()
            ->where('customer_id', $customerId)
            ->with([
                'order',
                'returnItems.orderItem.product',
            ])
            ->latest()
            ->get();
    }

    private function validateReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'The return reason is required.',
            ]);
        }
    }

    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (
                ! isset($item['order_item_id']) ||
                ! isset($item['quantity'])
            ) {
                throw ValidationException::withMessages([
                    'items' => 'Each return item must contain order_item_id and quantity.',
                ]);
            }

            $orderItemId = (int) $item['order_item_id'];
            $quantity = (int) $item['quantity'];

            if ($orderItemId <= 0 || $quantity <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Invalid return item data.',
                ]);
            }

            if (! isset($normalized[$orderItemId])) {
                $normalized[$orderItemId] = [
                    'order_item_id' => $orderItemId,
                    'quantity' => 0,
                ];
            }

            $normalized[$orderItemId]['quantity'] += $quantity;
        }

        return array_values($normalized);
    }

    private function normalizeShopifyItems(array $items): array
    {
        return collect($items)
            ->map(function (array $item): array {
                $quantity = (int) ($item['quantity'] ?? 0);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Each return item must have a valid quantity.',
                    ]);
                }

                return [
                    'shopify_line_item_id' => $item['shopify_line_item_id'] ?? null,
                    'title' => $item['title'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => isset($item['unit_price'])
                        ? (float) $item['unit_price']
                        : 0,
                ];
            })
            ->values()
            ->all();
    }

    private function getRemainingReturnableQuantity(OrderItem $orderItem): int
    {
        $alreadyReturnedQuantity = ReturnItem::query()
            ->where('order_item_id', $orderItem->id)
            ->whereHas('returnRequest', function ($query): void {
                $query->whereNotIn('status', [
                    ReturnRequest::STATUS_REJECTED,
                ]);
            })
            ->sum('quantity');

        return $orderItem->quantity - $alreadyReturnedQuantity;
    }

    private function getOrderItemUnitPrice(OrderItem $orderItem): float
    {
        return (float) (
            $orderItem->unit_price
            ?? $orderItem->price
            ?? 0
        );
    }

    private function calculateShopifyRefundAmount(array $items): float
    {
        return collect($items)->sum(function (array $item): float {
            return ((float) ($item['unit_price'] ?? 0)) * ((int) ($item['quantity'] ?? 0));
        });
    }

    private function loadReturnRequestRelations(ReturnRequest $returnRequest): ReturnRequest
    {
        return $returnRequest->load([
            'order',
            'customer',
            'returnItems.orderItem.product',
        ]);
    }
}
