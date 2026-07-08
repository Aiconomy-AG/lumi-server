<?php

namespace Modules\Sales\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $returnRequest->fill($validated)->save();

        return new ReturnRequestResource($returnRequest->fresh());
    }
}
