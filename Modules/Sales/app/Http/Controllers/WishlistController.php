<?php

namespace Modules\Sales\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Sales\Http\Requests\AddWishlistItemRequest;
use Modules\Sales\Services\WishlistService;
use Modules\Sales\Transformers\ProductResource;

class WishlistController
{
    public function __construct(
        private readonly WishlistService $wishlistService
    ) {
    }

    public function index(int $customerId): AnonymousResourceCollection
    {
        $products = $this->wishlistService->getProducts($customerId);

        return ProductResource::collection($products);
    }

    public function store(
        AddWishlistItemRequest $request,
        int $customerId
    ): AnonymousResourceCollection {
        $products = $this->wishlistService->addProduct(
            customerId: $customerId,
            productId: $request->integer('product_id'),
        );

        return ProductResource::collection($products);
    }

    public function destroy(
        int $customerId,
        int $productId
    ): AnonymousResourceCollection {
        $products = $this->wishlistService->removeProduct(
            customerId: $customerId,
            productId: $productId,
        );

        return ProductResource::collection($products);
    }
}
