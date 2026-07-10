<?php

namespace Modules\Sales\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Sales\Models\ReturnRequest;
use Modules\Sales\Services\ReturnService;
use Modules\Sales\Transformers\ReturnRequestResource;

class ReturnRequestController extends Controller
{
    public function __construct(
        private readonly ReturnService $returnService,
    ) {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in([
                ReturnRequest::STATUS_REQUESTED,
                ReturnRequest::STATUS_APPROVED,
                ReturnRequest::STATUS_REJECTED,
                ReturnRequest::STATUS_RECEIVED,
                ReturnRequest::STATUS_REFUNDED,
            ])],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 25;

        $query = ReturnRequest::query()
            ->with([
                'order',
                'customer',
                'returnItems.orderItem.variant.product',
            ])
            ->latest();

        if (($status = $validated['status'] ?? null) !== null) {
            $query->where('status', $status);
        }

        if (isset($validated['order_id'])) {
            $query->where('order_id', $validated['order_id']);
        }

        if (($search = $validated['search'] ?? null) !== null) {
            $query->where(function (Builder $q) use ($search): void {
                if (is_numeric($search)) {
                    $q->where('id', (int) $search);
                }

                $like = '%'.$search.'%';
                $q->orWhere('email', 'like', $like)
                    ->orWhere('shopify_order_name', 'like', $like);
            });
        }

        if (($from = $validated['from'] ?? null) !== null) {
            $query->whereDate('created_at', '>=', $from);
        }

        if (($to = $validated['to'] ?? null) !== null) {
            $query->whereDate('created_at', '<=', $to);
        }

        return ReturnRequestResource::collection($query->paginate($perPage));
    }

    public function show(int $returnRequestId): ReturnRequestResource
    {
        return new ReturnRequestResource(
            $this->returnService->getReturn($returnRequestId),
        );
    }

    public function updateNotes(Request $request, int $returnRequestId): ReturnRequestResource
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $returnRequest = ReturnRequest::query()->findOrFail($returnRequestId);
        $returnRequest->update([
            'notes' => $validated['notes'] ?? null,
        ]);

        return new ReturnRequestResource(
            $this->returnService->getReturn($returnRequest->id),
        );
    }

    public function approve(int $returnRequestId): ReturnRequestResource
    {
        return new ReturnRequestResource(
            $this->returnService->approveReturn($returnRequestId),
        );
    }

    public function reject(Request $request, int $returnRequestId): ReturnRequestResource
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return new ReturnRequestResource(
            $this->returnService->rejectReturn(
                $returnRequestId,
                $validated['notes'] ?? null,
            ),
        );
    }

    public function markReceived(int $returnRequestId): ReturnRequestResource
    {
        return new ReturnRequestResource(
            $this->returnService->markAsReceived($returnRequestId),
        );
    }

    public function markRefunded(int $returnRequestId): ReturnRequestResource
    {
        return new ReturnRequestResource(
            $this->returnService->markAsRefunded($returnRequestId),
        );
    }
}
