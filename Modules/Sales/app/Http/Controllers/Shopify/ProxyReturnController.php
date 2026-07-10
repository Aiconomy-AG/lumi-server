<?php

namespace Modules\Sales\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Models\Customer;
use Modules\Sales\Services\ReturnService;
use Modules\Sales\Services\Shopify\AppProxyVerifier;
use Modules\Sales\Support\ShopifyId;
use Modules\Sales\Transformers\ReturnRequestResource;

class ProxyReturnController extends Controller
{
    public function __construct(
        private readonly AppProxyVerifier $verifier,
        private readonly ReturnService $returnService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->verifier->verify($request)) {
            return response()->json([
                'message' => 'Invalid Shopify proxy signature.',
            ], 401);
        }

        $validated = $request->validate([
            'order_identifier' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.shopify_line_item_id' => ['nullable', 'string', 'max:255'],
            'items.*.shopify_product_id' => ['nullable', 'string', 'max:255'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],

            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $shopDomain = (string) $request->query('shop', '');

        if ($shopDomain === '') {
            return response()->json([
                'message' => 'Missing Shopify shop domain.',
            ], 422);
        }

        $shopifyCustomerId = ShopifyId::numeric(
            (string) $request->query('logged_in_customer_id', '')
        );

        $customer = null;

        if ($shopifyCustomerId !== null && $shopifyCustomerId !== '') {
            $customer = Customer::query()->firstOrCreate(
                [
                    'shopify_customer_id' => $shopifyCustomerId,
                ],
                [
                    'username' => 'Shopify Customer '.$shopifyCustomerId,
                    'email' => $validated['email'],
                ],
            );
        }

        $returnRequest = $this->returnService->createShopifyReturn(
            shopDomain: $shopDomain,
            email: $validated['email'],
            reason: $validated['reason'],
            items: $validated['items'],
            shopifyCustomerId: $shopifyCustomerId,
            shopifyOrderId: ShopifyId::orderGid($validated['order_identifier']),
            shopifyOrderName: $validated['order_identifier'],
            notes: $validated['notes'] ?? null,
        );

        if ($customer !== null && $returnRequest->customer_id === null) {
            $returnRequest->update([
                'customer_id' => $customer->id,
            ]);

            $returnRequest->refresh();
        }

        AuditLog::record(
            module: 'sales',
            action: 'return_requested',
            entity: $returnRequest,
            label: 'Return #'.$returnRequest->id.' ('.$validated['order_identifier'].')',
            changes: ['new' => ['status' => $returnRequest->status, 'reason' => $validated['reason']]],
            description: 'Return requested via Shopify proxy.',
            actorName: $customer?->email ?? $validated['email'],
        );

        return (new ReturnRequestResource($returnRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function ping(Request $request): JsonResponse
    {
        return response()->json([
            'query' => $request->query(),
            'has_signature' => $request->query('signature') !== null,
            'has_secret' => config('sales.shopify.client_secret') !== null
                && config('sales.shopify.client_secret') !== '',
            'secret_length' => strlen((string) config('sales.shopify.client_secret')),
        ]);
    }


}
