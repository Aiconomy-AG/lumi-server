<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Sales\Http\Requests\StoreCheckoutRequest;
use Modules\Sales\Services\CheckoutService;
use Modules\Sales\Transformers\OrderResource;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService
    ) {
    }

    public function store(StoreCheckoutRequest $request): JsonResponse
    {
        $order = $this->checkoutService->processCheckout(
            user: $request->user(),
            data: $request->validated()
        );

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function customerOrders(Request $request, int $customerId): AnonymousResourceCollection
    {
        $orders = $this->checkoutService->getCustomerOrders(
            user: $request->user(),
            customerId: $customerId
        );

        return OrderResource::collection($orders);
    }

    public function show(Request $request, int $orderId): OrderResource
    {
        $order = $this->checkoutService->getOrder(
            user: $request->user(),
            orderId: $orderId
        );

        return new OrderResource($order);
    }
}
