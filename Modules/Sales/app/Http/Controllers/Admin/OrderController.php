<?php

namespace Modules\Sales\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Sales\Models\Order;
use Modules\Sales\Transformers\OrderResource;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'processing', 'shipped', 'delivered', 'cancelled'])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 25;

        $query = Order::query()
            ->with(['items.variant.product', 'customer'])
            ->latest();

        if (($status = $validated['status'] ?? null) !== null) {
            $query->whereApiStatus($status);
        }

        if (isset($validated['customer_id'])) {
            $query->where('customer_id', $validated['customer_id']);
        }

        if (($search = $validated['search'] ?? null) !== null) {
            $query->where(function (Builder $q) use ($search): void {
                if (is_numeric($search)) {
                    $q->where('id', (int) $search);
                }

                $like = '%'.$search.'%';
                $q->orWhere('shopify_order_name', 'like', $like)
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($like): void {
                        $customerQuery->where('email', 'like', $like)
                            ->orWhere('username', 'like', $like);
                    });
            });
        }

        if (($from = $validated['from'] ?? null) !== null) {
            $query->whereDate('created_at', '>=', $from);
        }

        if (($to = $validated['to'] ?? null) !== null) {
            $query->whereDate('created_at', '<=', $to);
        }

        return OrderResource::collection($query->paginate($perPage));
    }

    public function show(int $orderId): OrderResource
    {
        $order = Order::query()
            ->with([
                'items.variant.product',
                'customer',
                'returnRequests',
            ])
            ->findOrFail($orderId);

        return new OrderResource($order);
    }
}
