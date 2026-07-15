<?php

namespace Modules\Sales\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\Order;
use Modules\Sales\Models\Product;

class CheckoutService
{
    public function processCheckout(User $user, array $data): Order
    {
        $customer = Customer::resolveFromUser($user);

        $dbStatus = $this->mapPaymentStatus($data['payment_status'] ?? null);
        $dbPaymentStatus = $this->mapFulfillmentStatus($data['status'] ?? null);

        [$subtotal, $itemsData] = $this->calculateTotalsAndItems($data['items']);
        $shippingCost = (float) ($data['shipping_cost'] ?? 5.00);
        $totalAmount = $subtotal + $shippingCost;

        $order = DB::transaction(function () use ($customer, $dbStatus, $dbPaymentStatus, $subtotal, $shippingCost, $totalAmount, $data, $itemsData) {
            $order = Order::create([
                'customer_id' => $customer->id,
                'status' => $dbStatus,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total_amount' => $totalAmount,
                'shipping_address' => $data['shipping_address'],
                'payment_method' => $data['payment_method'],
                'payment_status' => $dbPaymentStatus,
            ]);

            $order->items()->createMany($itemsData);

            return $order;
        });

        $this->logAudit($user, $order);

        return $order->load('items');
    }

    public function getCustomerOrders(User $user, int $customerId)
    {
        $targetCustomer = Customer::findOrFail($customerId);
        $customer = Customer::resolveFromUser($user);

        if (!$customer || (!$user->isAdmin() && $customer->id != $customerId)) {
            throw new AuthorizationException('Unauthorized access.');
        }

        return Order::with('items')->where('customer_id', $customerId)->get();
    }

    public function getOrder(User $user, int $orderId): Order
    {
        $order = Order::with('items')->findOrFail($orderId);
        $customer = Customer::resolveFromUser($user);

        if (!$customer || (!$user->isAdmin() && $order->customer_id != $customer->id)) {
            throw new AuthorizationException('Unauthorized access to this order.');
        }

        return $order;
    }

    private function mapPaymentStatus(?string $status): string
    {
        return match ($status) {
            'successful' => 'paid',
            'failed' => 'voided',
            default => 'pending',
        };
    }

    private function mapFulfillmentStatus(?string $status): string
    {
        return match ($status) {
            'shipped' => 'shipped',
            'delivered' => 'fulfilled',
            default => 'unshipped',
        };
    }

    private function calculateTotalsAndItems(array $items): array
    {
        $subtotal = 0.00;
        $itemsData = [];

        foreach ($items as $item) {
            $product = Product::with('variants')->findOrFail($item['product_id']);
            $variant = $product->variants->first(fn ($candidate) => (float) $candidate->price > 0)
                ?? $product->variants->first();

            if ($variant === null) {
                throw new \InvalidArgumentException("Product {$product->id} has no variants.");
            }

            $price = (float) $variant->price;

            if ($price <= 0.00) {
                $price = (float) $product->price;
            }

            $subtotal += $price * $item['quantity'];
            $itemsData[] = [
                'product_variant_id' => $variant->id,
                'quantity' => $item['quantity'],
                'unit_price' => $price,
            ];
        }

        return [$subtotal, $itemsData];
    }

    private function logAudit(User $user, Order $order): void
    {
        AuditLog::record(
            module: 'sales',
            action: 'order_created',
            entity: $order,
            label: 'Order #'.$order->id,
            changes: ['new' => ['total_amount' => $order->total_amount, 'status' => $order->status]],
            description: 'Order placed via checkout.',
            actor: $user,
        );
    }
}
