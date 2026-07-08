<?php

namespace Modules\Sales\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\Order;
use Modules\Sales\Models\Product;
use Modules\Sales\Services\Shopify\WebhookVerifier;
use Modules\Sales\Support\ShopifyId;

class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookVerifier $verifier,
    ) {}

    public function customer(Request $request): JsonResponse
    {
        if (! $this->verifier->verify($request)) {
            return response()->json(['message' => 'Invalid Shopify webhook signature.'], 401);
        }

        $payload = $request->json()->all();
        $this->upsertCustomer($payload);

        return response()->json(['ok' => true]);
    }

    public function order(Request $request): JsonResponse
    {
        if (! $this->verifier->verify($request)) {
            return response()->json(['message' => 'Invalid Shopify webhook signature.'], 401);
        }

        $payload = $request->json()->all();

        DB::transaction(function () use ($payload): void {
            $customer = $this->upsertCustomer($payload['customer'] ?? []);

            $order = Order::query()->updateOrCreate(
                ['shopify_order_id' => ShopifyId::orderGid((string) ($payload['admin_graphql_api_id'] ?? $payload['id'] ?? ''))],
                [
                    'customer_id' => $customer->id,
                    'shopify_order_name' => (string) ($payload['name'] ?? $payload['order_number'] ?? ''),
                    'shopify_customer_id' => $customer->shopify_customer_id,
                    'status' => $this->financialStatus($payload['financial_status'] ?? null),
                    'subtotal' => (float) ($payload['subtotal_price'] ?? 0),
                    'shipping_cost' => $this->shippingTotal($payload),
                    'total_amount' => (float) ($payload['total_price'] ?? 0),
                    'shipping_address' => $this->shippingAddress($payload['shipping_address'] ?? []),
                    'payment_method' => $this->paymentMethod($payload),
                    'payment_status' => $this->fulfillmentStatus($payload['fulfillment_status'] ?? null),
                ],
            );

            $order->items()->delete();

            foreach (($payload['line_items'] ?? []) as $lineItem) {
                $shopifyProductId = ShopifyId::productGid((string) ($lineItem['product_id'] ?? ''));
                $product = $shopifyProductId
                    ? Product::query()->where('shopify_product_id', $shopifyProductId)->first()
                    : null;

                if ($product) {
                    $order->items()->create([
                        'product_id' => $product->id,
                        'quantity' => max(1, (int) ($lineItem['quantity'] ?? 1)),
                    ]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    public function product(Request $request): JsonResponse
    {
        if (! $this->verifier->verify($request)) {
            return response()->json(['message' => 'Invalid Shopify webhook signature.'], 401);
        }

        $payload = $request->json()->all();
        $shopifyProductId = ShopifyId::productGid((string) ($payload['admin_graphql_api_id'] ?? $payload['id'] ?? ''));

        if ($shopifyProductId !== null) {
            Product::query()
                ->where('shopify_product_id', $shopifyProductId)
                ->update([
                    'name' => (string) ($payload['title'] ?? ''),
                    'description' => $payload['body_html'] ?? null,
                    'price' => (float) ($payload['variants'][0]['price'] ?? 0),
                ]);
        }

        return response()->json(['ok' => true]);
    }

    private function upsertCustomer(array $payload): Customer
    {
        $shopifyCustomerId = ShopifyId::numeric((string) ($payload['id'] ?? $payload['admin_graphql_api_id'] ?? ''));

        if ($shopifyCustomerId === null || $shopifyCustomerId === '') {
            $shopifyCustomerId = 'unknown-'.md5(json_encode($payload));
        }

        $name = trim(implode(' ', array_filter([
            $payload['first_name'] ?? null,
            $payload['last_name'] ?? null,
        ])));

        return Customer::query()->updateOrCreate(
            ['shopify_customer_id' => $shopifyCustomerId],
            [
                'username' => $name !== '' ? $name : 'Shopify Customer '.$shopifyCustomerId,
                'email' => $payload['email'] ?? null,
            ],
        );
    }

    private function financialStatus(?string $status): string
    {
        return match ($status) {
            'paid', 'pending', 'authorized', 'partially_paid', 'partially_refunded', 'refunded', 'voided', 'expired' => $status,
            default => 'pending',
        };
    }

    private function fulfillmentStatus(?string $status): string
    {
        return match ($status) {
            'shipped', 'fulfilled', 'partial', 'scheduled', 'on_hold', 'unfulfilled', 'request_declined' => $status,
            default => 'unfulfilled',
        };
    }

    private function shippingTotal(array $payload): float
    {
        $lines = $payload['shipping_lines'] ?? [];

        return collect(is_array($lines) ? $lines : [])->sum(fn ($line) => (float) ($line['price'] ?? 0));
    }

    private function shippingAddress(array $address): string
    {
        $parts = array_filter([
            $address['address1'] ?? null,
            $address['address2'] ?? null,
            $address['city'] ?? null,
            $address['province'] ?? null,
            $address['country'] ?? null,
            $address['zip'] ?? null,
        ]);

        return implode(', ', $parts) ?: 'Synced from Shopify';
    }

    private function paymentMethod(array $payload): string
    {
        $methods = $payload['payment_gateway_names'] ?? [];

        if (is_array($methods) && $methods !== []) {
            return implode(', ', array_map('strval', $methods));
        }

        return (string) ($payload['gateway'] ?? 'Shopify');
    }
}
