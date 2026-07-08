<?php

namespace Modules\Sales\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\ReturnRequest;
use Modules\Sales\Services\Shopify\AppProxyVerifier;
use Modules\Sales\Support\ShopifyId;
use Modules\Sales\Transformers\ReturnRequestResource;

class ProxyReturnController extends Controller
{
    public function __construct(
        private readonly AppProxyVerifier $verifier,
    ) {}

    public function store(Request $request): JsonResponse
    {
        if (! $this->verifier->verify($request)) {
            return response()->json(['message' => 'Invalid Shopify proxy signature.'], 401);
        }

        $validated = $request->validate([
            'order_identifier' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'items' => ['nullable', 'array'],
            'items.*.shopify_product_id' => ['nullable', 'string', 'max:255'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $shopifyCustomerId = ShopifyId::numeric((string) $request->query('logged_in_customer_id', ''));
        $customer = null;

        if ($shopifyCustomerId !== null && $shopifyCustomerId !== '') {
            $customer = Customer::query()->firstOrCreate(
                ['shopify_customer_id' => $shopifyCustomerId],
                [
                    'username' => 'Shopify Customer '.$shopifyCustomerId,
                    'email' => $validated['email'] ?? null,
                ],
            );
        }

        $returnRequest = ReturnRequest::query()->create([
            'customer_id' => $customer?->id,
            'shop_domain' => (string) $request->query('shop', ''),
            'shopify_customer_id' => $shopifyCustomerId,
            'shopify_order_id' => ShopifyId::orderGid($validated['order_identifier']),
            'shopify_order_name' => $validated['order_identifier'],
            'email' => $validated['email'] ?? $customer?->email,
            'items' => $validated['items'] ?? [],
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'requested',
        ]);

        return (new ReturnRequestResource($returnRequest))
            ->response()
            ->setStatusCode(201);
    }
}
