<?php

namespace Modules\Sales\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Sales\Http\Requests\AddCartItemRequest;
use Modules\Sales\Http\Requests\UpdateCartItemRequest;
use Modules\Sales\Services\CartService;
use Modules\Sales\Transformers\CartResource;

class CartController
{
    public function __construct(
        private readonly CartService $cartService
    ) {
    }

    public function show(int $customerId): CartResource
    {
        $cart = $this->cartService->getCart($customerId);

        return new CartResource($cart);
    }

    public function storeItem(
        AddCartItemRequest $request,
        int $customerId
    ): JsonResponse {
        $result = $this->cartService->addItem(
            customerId: $customerId,
            productVariantId: $request->integer('product_variant_id'),
            quantity: $request->integer('quantity'),
        );

        return (new CartResource($result['cart']))
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }

    public function updateItem(
        UpdateCartItemRequest $request,
        int $customerId,
        int $productVariantId
    ): CartResource {
        $cart = $this->cartService->updateItem(
            customerId: $customerId,
            productVariantId: $productVariantId,
            quantity: $request->integer('quantity'),
        );

        return new CartResource($cart);
    }

    public function destroyItem(
        int $customerId,
        int $productVariantId
    ): CartResource {
        $cart = $this->cartService->removeItem(
            customerId: $customerId,
            productVariantId: $productVariantId,
        );

        return new CartResource($cart);
    }
}
