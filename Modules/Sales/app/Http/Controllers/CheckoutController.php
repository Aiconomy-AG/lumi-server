<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\Order;
use Modules\Sales\Models\Product;
use Modules\Sales\Transformers\OrderResource;

class CheckoutController extends Controller
{
    /**
     * Create a new order (POST /shop/orders).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shipping_address' => 'required|string|max:255',
            'payment_method' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'status' => 'nullable|string|in:pending,processing,shipped,delivered,cancelled',
            'payment_status' => 'nullable|string|in:pending,successful,failed',
            'subtotal' => 'nullable|numeric',
            'shipping_cost' => 'nullable|numeric',
            'total_amount' => 'nullable|numeric',
        ]);

        $user = $request->user();

        $customer = Customer::resolveFromUser($user);

        $dbStatus = 'pending';
        if (($validated['payment_status'] ?? null) === 'successful') {
            $dbStatus = 'paid';
        } elseif (($validated['payment_status'] ?? null) === 'failed') {
            $dbStatus = 'voided';
        }

        $dbPaymentStatus = 'unshipped';
        if (($validated['status'] ?? null) === 'shipped') {
            $dbPaymentStatus = 'shipped';
        } elseif (($validated['status'] ?? null) === 'delivered') {
            $dbPaymentStatus = 'fulfilled';
        }

        $subtotal = 0.00;
        $itemsData = [];

        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            $price = (float) $product->price;

            if ($price <= 0.00) {
                $firstVariant = $product->variants()->first();
                if ($firstVariant) {
                    $price = (float) $firstVariant->price;
                }
            }

            $subtotal += $price * $item['quantity'];

            $itemsData[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
            ];
        }

        $shippingCost = isset($validated['shipping_cost']) ? (float) $validated['shipping_cost'] : 5.00;
        $totalAmount = $subtotal + $shippingCost;

        $order = DB::transaction(function () use ($customer, $dbStatus, $dbPaymentStatus, $subtotal, $shippingCost, $totalAmount, $validated, $itemsData) {
            $order = Order::create([
                'customer_id' => $customer->id,
                'status' => $dbStatus,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total_amount' => $totalAmount,
                'shipping_address' => $validated['shipping_address'],
                'payment_method' => $validated['payment_method'],
                'payment_status' => $dbPaymentStatus,
            ]);

            foreach ($itemsData as $item) {
                $order->items()->create($item);
            }

            return $order;
        });

        AuditLog::record(
            module: 'sales',
            action: 'order_created',
            entity: $order,
            label: 'Order #'.$order->id,
            changes: ['new' => ['total_amount' => $order->total_amount, 'status' => $order->status]],
            description: 'Order placed via checkout.',
            actor: $user,
        );

        $order->load('items');

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get customer order history (GET /shop/customers/{customerId}/orders).
     */
    public function customerOrders(Request $request, $customerId)
    {
        $targetCustomer = Customer::find($customerId);
        if (!$targetCustomer) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Customer not found.'
            ], 404);
        }

        $user = $request->user();
        $customer = Customer::resolveFromUser($user);

        if (!$customer || (!$user->isAdmin() && $customer->id != $customerId)) {
            return response()->json([
                'code' => 'UNAUTHORIZED',
                'message' => 'Unauthorized access.'
            ], 401);
        }

        $orders = Order::with('items')
            ->where('customer_id', $customerId)
            ->get();

        return OrderResource::collection($orders);
    }

    /**
     * Get order details by ID (GET /shop/orders/{orderId}).
     */
    public function show(Request $request, $orderId)
    {
        $order = Order::with('items')->find($orderId);

        if (!$order) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Order not found.'
            ], 404);
        }

        $user = $request->user();
        $customer = Customer::resolveFromUser($user);

        // Restrict to owner unless requesting user is an admin
        if (!$customer || (!$user->isAdmin() && $order->customer_id != $customer->id)) {
            return response()->json([
                'code' => 'UNAUTHORIZED',
                'message' => 'Unauthorized access to this order.'
            ], 401);
        }

        return new OrderResource($order);
    }
}
