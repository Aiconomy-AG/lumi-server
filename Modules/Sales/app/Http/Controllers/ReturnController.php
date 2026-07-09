<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Http\Requests\CreateReturnRequest;
use Modules\Sales\Http\Requests\RejectReturnRequest;
use Modules\Sales\Http\Resources\ReturnRequestResource;
use Modules\Sales\Services\ReturnService;

class ReturnController extends Controller
{
    public function __construct(
        private readonly ReturnService $returnService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $shopDomain = $request->query('shop_domain');

        $returns = $shopDomain
            ? $this->returnService->getReturnsForShop($shopDomain, $status)
            : $this->returnService->getReturnsForAdmin($status);

        return response()->json([
            'data' => ReturnRequestResource::collection($returns),
        ]);
    }

    public function store(CreateReturnRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['order_id'], $data['customer_id'])) {
            $returnRequest = $this->returnService->createReturnFromOrder(
                customerId: $data['customer_id'],
                orderId: $data['order_id'],
                reason: $data['reason'],
                items: $data['items'],
                notes: $data['notes'] ?? null,
            );
        } else {
            $returnRequest = $this->returnService->createShopifyReturn(
                shopDomain: $data['shop_domain'],
                email: $data['email'],
                reason: $data['reason'],
                items: $data['items'],
                shopifyCustomerId: $data['shopify_customer_id'] ?? null,
                shopifyOrderId: $data['shopify_order_id'] ?? null,
                shopifyOrderName: $data['shopify_order_name'] ?? null,
                notes: $data['notes'] ?? null,
            );
        }

        return response()->json([
            'message' => 'Return request created successfully.',
            'data' => new ReturnRequestResource($returnRequest),
        ], 201);
    }

    public function show(int $returnRequestId): JsonResponse
    {
        $returnRequest = $this->returnService->getReturn($returnRequestId);

        return response()->json([
            'data' => new ReturnRequestResource($returnRequest),
        ]);
    }

    public function approve(int $returnRequestId): JsonResponse
    {
        $returnRequest = $this->returnService->approveReturn($returnRequestId);

        return response()->json([
            'message' => 'Return request approved successfully.',
            'data' => new ReturnRequestResource($returnRequest),
        ]);
    }

    public function reject(
        RejectReturnRequest $request,
        int $returnRequestId
    ): JsonResponse {
        $returnRequest = $this->returnService->rejectReturn(
            returnRequestId: $returnRequestId,
            notes: $request->validated('notes'),
        );

        return response()->json([
            'message' => 'Return request rejected successfully.',
            'data' => new ReturnRequestResource($returnRequest),
        ]);
    }

    public function markAsReceived(int $returnRequestId): JsonResponse
    {
        $returnRequest = $this->returnService->markAsReceived($returnRequestId);

        return response()->json([
            'message' => 'Return request marked as received successfully.',
            'data' => new ReturnRequestResource($returnRequest),
        ]);
    }

    public function markAsRefunded(int $returnRequestId): JsonResponse
    {
        $returnRequest = $this->returnService->markAsRefunded($returnRequestId);

        return response()->json([
            'message' => 'Return request marked as refunded successfully.',
            'data' => new ReturnRequestResource($returnRequest),
        ]);
    }
}
