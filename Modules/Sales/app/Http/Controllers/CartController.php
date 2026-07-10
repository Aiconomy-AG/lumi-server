<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Sales\Models\Cart;
use Modules\Sales\Models\CartItem;
use Modules\Sales\Models\ProductVariant;

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
        int $productVariantId,
        int $quantity
    ): array {
        return DB::transaction(function () use (
            $customerId,
            $productVariantId,
            $quantity
        ): array {
            $variant = ProductVariant::query()
                ->lockForUpdate()
                ->findOrFail($productVariantId);

            $cart = Cart::query()->firstOrCreate([
                'customer_id' => $customerId,
            ]);

            $cartItem = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_variant_id', $productVariantId)
                ->lockForUpdate()
                ->first();

            $created = $cartItem === null;

            $newQuantity = $cartItem
                ? $cartItem->quantity + $quantity
                : $quantity;

            $this->validateQuantity(
                variant: $variant,
                quantity: $newQuantity,
            );

            if ($cartItem) {
                $cartItem->update([
                    'quantity' => $newQuantity,
                ]);
            } else {
                CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'product_variant_id' => $productVariantId,
                    'quantity' => $newQuantity,
                ]);
            }

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
        return DB::transaction(function () use (
            $customerId,
            $productVariantId,
            $quantity
        ): Cart {
            $variant = ProductVariant::query()
                ->lockForUpdate()
                ->findOrFail($productVariantId);

            $cart = Cart::query()
                ->where('customer_id', $customerId)
                ->firstOrFail();

            $cartItem = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_variant_id', $productVariantId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateQuantity(
                variant: $variant,
                quantity: $quantity,
            );

            $cartItem->update([
                'quantity' => $quantity,
            ]);

            return $this->loadCart($cart);
        });
    }

    public function removeItem(
        int $customerId,
        int $productVariantId
    ): Cart {
        return DB::transaction(function () use (
            $customerId,
            $productVariantId
        ): Cart {
            $cart = Cart::query()
                ->where('customer_id', $customerId)
                ->firstOrFail();

            $cartItem = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_variant_id', $productVariantId)
                ->lockForUpdate()
                ->firstOrFail();

            $cartItem->delete();

            return $this->loadCart($cart);
        });
    }

    private function validateQuantity(
        ProductVariant $variant,
        int $quantity
    ): void {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 1.',
            ]);
        }

        if ($quantity > $variant->stock_quantity) {
            throw ValidationException::withMessages([
                'quantity' => sprintf(
                    'Only %d item(s) are available in stock.',
                    $variant->stock_quantity
                ),
            ]);
        }
    }

    private function loadCart(Cart $cart): Cart
    {
        return $cart->fresh()->load([
            'items.variant.product',
            'items.variant.product.ingredients',
        ]);
    }
}
