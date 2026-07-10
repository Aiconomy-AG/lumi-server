<?php

namespace Modules\Workspace\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Sales\Models\ReturnRequest;
use Modules\Workspace\Http\Requests\UpdateReturnRequest;
use Modules\Workspace\Transformers\ReturnRequestResource;

class ReturnRequestController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 25);

        $returns = ReturnRequest::query()
            ->with('returnItems')
            ->latest()
            ->paginate($perPage);

        return ReturnRequestResource::collection($returns);
    }

    public function show(int $returnRequestId): ReturnRequestResource
    {
        $returnRequest = ReturnRequest::query()
            ->with('returnItems.orderItem')
            ->findOrFail($returnRequestId);

        return new ReturnRequestResource($returnRequest);
    }

    public function update(
        UpdateReturnRequest $request,
        int $returnRequestId
    ): ReturnRequestResource {
        $validated = $request->validated();

        $returnRequest = ReturnRequest::query()->findOrFail($returnRequestId);
        $oldStatus = $returnRequest->status;

        $returnRequest->fill($validated)->save();

        if ($returnRequest->status !== $oldStatus) {
            AuditLog::record(
                module: 'workspace',
                action: 'return_status_change',
                entity: $returnRequest,
                label: 'Return #'.$returnRequest->id,
                changes: [
                    'old' => ['status' => $oldStatus],
                    'new' => ['status' => $returnRequest->status],
                ],
            );
        }

        return new ReturnRequestResource(
            $returnRequest->fresh()->load('returnItems.orderItem')
        );
    }
}
