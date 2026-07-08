<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\Cart;
use Modules\Sales\Models\CartItem;
use Modules\Sales\Models\ProductVariant;

class CartService
{
    public function getCart(int $customerId): Cart
    {
        $cart = Cart::firstOrCreate([
            'customer_id' => $customerId,
        ]);

        return $this->loadCart($cart);
    }

    public function addItem(
        int $customerId,
        int $productVariantId,
        int $quantity
    ): array {
        return DB::transaction(function () use (
            $customerId,
            $productVariantId,
            $quantity
        ): array {
            ProductVariant::query()->findOrFail($productVariantId);

            $cart = Cart::firstOrCreate([
                'customer_id' => $customerId,
            ]);

            $cartItem = CartItem::query()->firstOrNew([
                'cart_id' => $cart->id,
                'product_variant_id' => $productVariantId,
            ]);

            $created = ! $cartItem->exists;

            if ($cartItem->exists) {
                $cartItem->quantity += $quantity;
            } else {
                $cartItem->quantity = $quantity;
            }

            $cartItem->save();

            return [
                'cart' => $this->loadCart($cart),
                'created' => $created,
            ];
        });
    }

    public function updateItem(
        int $customerId,
        int $productVariantId,
        int $quantity
    ): Cart {
        $cart = Cart::query()
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $cartItem = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_variant_id', $productVariantId)
            ->firstOrFail();

        $cartItem->update([
            'quantity' => $quantity,
        ]);

        return $this->loadCart($cart);
    }

    public function removeItem(
        int $customerId,
        int $productVariantId
    ): Cart {
        $cart = Cart::query()
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $cartItem = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_variant_id', $productVariantId)
            ->firstOrFail();

        $cartItem->delete();

        return $this->loadCart($cart);
    }

    private function loadCart(Cart $cart): Cart
    {
        return $cart->load([
            'items.variant.product',
            'items.variant.product.ingredients',
        ]);
    }
}
