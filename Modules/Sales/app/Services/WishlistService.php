<?php

namespace Modules\Sales\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\WishlistItem;

class WishlistService
{
    public function getProducts(int $customerId): Collection
    {
        return Product::query()
            ->whereHas('wishlistItems', function ($query) use ($customerId): void {
                $query->where('customer_id', $customerId);
            })
            ->with([
                'variants',
                'ingredients',
            ])
            ->orderBy('name')
            ->get();
    }

    public function addProduct(
        int $customerId,
        int $productId
    ): Collection {
        DB::transaction(function () use ($customerId, $productId): void {
            Product::query()->findOrFail($productId);

            WishlistItem::query()->firstOrCreate([
                'customer_id' => $customerId,
                'product_id' => $productId,
            ]);
        });

        return $this->getProducts($customerId);
    }

    public function removeProduct(
        int $customerId,
        int $productId
    ): Collection {
        WishlistItem::query()
            ->where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->firstOrFail()
            ->delete();

        return $this->getProducts($customerId);
    }
}
