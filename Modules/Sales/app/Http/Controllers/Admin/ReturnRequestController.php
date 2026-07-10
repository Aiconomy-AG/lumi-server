<?php

namespace Modules\Sales\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Modules\Sales\Models\ReturnRequest;
use Modules\Sales\Transformers\ReturnRequestResource;

class ReturnRequestController extends Controller
{
    public function index()
    {
        return ReturnRequestResource::collection(
            ReturnRequest::query()->latest()->paginate(25),
        );
    }

    public function show(int $returnRequestId): ReturnRequestResource
    {
        return new ReturnRequestResource(ReturnRequest::query()->findOrFail($returnRequestId));
    }

    public function update(Request $request, int $returnRequestId): ReturnRequestResource
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:requested,approved,rejected,received,refunded'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $returnRequest = ReturnRequest::query()->findOrFail($returnRequestId);
        $oldStatus = $returnRequest->status;

        $returnRequest->fill($validated)->save();

        if ($returnRequest->status !== $oldStatus) {
            AuditLog::record(
                module: 'sales',
                action: 'return_status_change',
                entity: $returnRequest,
                label: 'Return #'.$returnRequest->id,
                changes: [
                    'old' => ['status' => $oldStatus],
                    'new' => ['status' => $returnRequest->status],
                ],
            );
        }

        return new ReturnRequestResource($returnRequest->fresh());
    }
}
