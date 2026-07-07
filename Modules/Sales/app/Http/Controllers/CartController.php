<?php

namespace Modules\Sales\Http\Controllers;

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
    ): CartResource {
        $cart = $this->cartService->addItem(
            customerId: $customerId,
            productId: $request->integer('product_id'),
            quantity: $request->integer('quantity'),
        );

        return new CartResource($cart);
    }

    public function updateItem(
        UpdateCartItemRequest $request,
        int $customerId,
        int $productId
    ): CartResource {
        $cart = $this->cartService->updateItem(
            customerId: $customerId,
            productId: $productId,
            quantity: $request->integer('quantity'),
        );

        return new CartResource($cart);
    }

    public function destroyItem(
        int $customerId,
        int $productId
    ): CartResource {
        $cart = $this->cartService->removeItem(
            customerId: $customerId,
            productId: $productId,
        );

        return new CartResource($cart);
    }
}
