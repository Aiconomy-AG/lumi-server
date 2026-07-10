<?php

namespace Modules\Sales\Http\Controllers\Shopify;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Http\Requests\AddCartItemRequest;
use Modules\Sales\Http\Requests\UpdateCartItemRequest;
use Modules\Sales\Models\Customer;
use Modules\Sales\Services\CartService;
use Modules\Sales\Transformers\CartResource;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class ProxyCartController
{
    public function __construct(
        private readonly CartService $cartService
    ) {
    }

    public function show(Request $request): CartResource
    {
        $customer = $this->resolveCustomer($request);

        $cart = $this->cartService->getCart($customer->id);

        return new CartResource($cart);
    }

    public function storeItem(
        AddCartItemRequest $request
    ): JsonResponse {
        $customer = $this->resolveCustomer($request);

        $result = $this->cartService->addItem(
            customerId: $customer->id,
            productVariantId: $request->integer(
                'product_variant_id'
            ),
            quantity: $request->integer('quantity'),
        );

        return (new CartResource($result['cart']))
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }

    public function updateItem(
        UpdateCartItemRequest $request,
        int $productVariantId
    ): CartResource {
        $customer = $this->resolveCustomer($request);

        $cart = $this->cartService->updateItem(
            customerId: $customer->id,
            productVariantId: $productVariantId,
            quantity: $request->integer('quantity'),
        );

        return new CartResource($cart);
    }

    public function destroyItem(
        Request $request,
        int $productVariantId
    ): CartResource {
        $customer = $this->resolveCustomer($request);

        $cart = $this->cartService->removeItem(
            customerId: $customer->id,
            productVariantId: $productVariantId,
        );

        return new CartResource($cart);
    }

    private function resolveCustomer(Request $request): Customer
    {
        $shopifyCustomerId = $request->query(
            'logged_in_customer_id'
        );

        if (
            $shopifyCustomerId === null ||
            $shopifyCustomerId === ''
        ) {
            throw new UnauthorizedHttpException(
                'Shopify',
                'You must be logged in to access the persistent cart.'
            );
        }

        return Customer::query()
            ->where(
                'shopify_customer_id',
                (string) $shopifyCustomerId
            )
            ->firstOrFail();
    }
}
