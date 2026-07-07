<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\Cart;
use Modules\Sales\Models\CartItem;
use Modules\Sales\Models\Product;

class CartService
{
    public function getCart(int $customerId): Cart
    {
        $cart = Cart::query()->firstOrCreate([
            'customer_id' => $customerId,
        ]);

        return $this->loadCart($cart);
    }

    public function addItem(
        int $customerId,
        int $productId,
        int $quantity
    ): Cart {
        return DB::transaction(function () use (
            $customerId,
            $productId,
            $quantity
        ): Cart {
            Product::query()->findOrFail($productId);

            $cart = Cart::query()->firstOrCreate([
                'customer_id' => $customerId,
            ]);

            $item = CartItem::query()->firstOrNew([
                'cart_id' => $cart->id,
                'product_id' => $productId,
            ]);

            $item->quantity = $item->exists
                ? $item->quantity + $quantity
                : $quantity;

            $item->save();

            return $this->loadCart($cart);
        });
    }

    public function updateItem(
        int $customerId,
        int $productId,
        int $quantity
    ): Cart {
        return DB::transaction(function () use (
            $customerId,
            $productId,
            $quantity
        ): Cart {
            $cart = Cart::query()
                ->where('customer_id', $customerId)
                ->firstOrFail();

            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $productId)
                ->firstOrFail();

            $item->update([
                'quantity' => $quantity,
            ]);

            return $this->loadCart($cart);
        });
    }

    public function removeItem(
        int $customerId,
        int $productId
    ): Cart {
        return DB::transaction(function () use (
            $customerId,
            $productId
        ): Cart {
            $cart = Cart::query()
                ->where('customer_id', $customerId)
                ->firstOrFail();

            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $productId)
                ->firstOrFail();

            $item->delete();

            return $this->loadCart($cart);
        });
    }

    private function loadCart(Cart $cart): Cart
    {
        return $cart->load([
            'items.product.variants',
            'items.product.ingredients',
        ]);
    }
}
