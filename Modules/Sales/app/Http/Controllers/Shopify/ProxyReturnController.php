<?php

namespace Modules\Sales\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\Order;
use Modules\Sales\Models\ReturnRequest;
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
        if (! $this->verifier->verify($request, $this->proxySecretConfigKeys())) {
            return response()->json([
                'message' => 'Invalid Shopify proxy signature.',
            ], 401);
        }

        $validated = $request->validate([
            'order_id' => ['required', 'string', 'max:255'],
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
            shopifyOrderId: ShopifyId::orderGid($validated['order_id']),
            shopifyOrderName: $validated['order_id'],
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
            label: 'Return #'.$returnRequest->id.' ('.$validated['order_id'].')',
            changes: [
                'new' => [
                    'status' => $returnRequest->status,
                    'reason' => $validated['reason'],
                ],
            ],
            description: 'Return requested via Shopify proxy.',
            actorName: $customer?->email ?? $validated['email'],
        );

        return (new ReturnRequestResource($returnRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function lookupFromCustomerAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shopify_order_id' => ['required', 'string', 'max:255'],
        ]);

        $shopifyOrderId = $validated['shopify_order_id'];
        $orderIdCandidates = $this->shopifyOrderIdCandidates($shopifyOrderId);

        $order = Order::query()
            ->with([
                'customer',
                'items.productVariant.product',
            ])
            ->whereIn('shopify_order_id', $orderIdCandidates)
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'Order not found.',
                'searched_ids' => $orderIdCandidates,
            ], 404);
        }

        $customer = $order->customer;

        if ($customer === null && $order->shopify_customer_id !== null) {
            $customer = Customer::query()
                ->where('shopify_customer_id', $order->shopify_customer_id)
                ->first();
        }

        return response()->json([
            'order' => [
                'name' => $order->shopify_order_name,
                'shopify_order_id' => $order->shopify_order_id,
                'email' => $customer?->email,
            ],
            'items' => $order->items->map(function ($item): array {
                $variant = $item->productVariant;
                $product = $variant?->product;

                $productName = $product?->name
                    ?? $product?->title
                    ?? null;

                $variantName = $variant?->name
                    ?? $variant?->title
                    ?? null;

                if ($productName !== null && $variantName !== null) {
                    $title = $productName.' - '.$variantName;
                } elseif ($productName !== null) {
                    $title = $productName;
                } elseif ($variantName !== null) {
                    $title = $variantName;
                } else {
                    $title = 'Product variant #'.$item->product_variant_id;
                }

                return [
                    'shopify_line_item_id' => null,
                    'shopify_product_id' => $product?->shopify_product_id
                        ?? $variant?->shopify_product_id
                            ?? null,

                    'product_variant_id' => $item->product_variant_id,
                    'title' => $title,
                    'unit_price' => (float) $item->unit_price,
                    'quantity' => (int) $item->quantity,
                ];
            })->values(),
        ]);
    }

    private function shopifyOrderIdCandidates(string $value): array
    {
        $candidates = [$value];

        $numericId = ShopifyId::numeric($value);

        if ($numericId !== null && $numericId !== '') {
            $candidates[] = $numericId;
            $candidates[] = ShopifyId::orderGid($numericId);
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    public function storeFromCustomerAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string', 'max:255'],
            'shopify_order_id' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.shopify_line_item_id' => ['nullable', 'string', 'max:255'],
            'items.*.shopify_product_id' => ['nullable', 'string', 'max:255'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],

            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = Order::query()
            ->whereIn(
                'shopify_order_id',
                $this->shopifyOrderIdCandidates(
                    $validated['shopify_order_id']
                )
            )
            ->firstOrFail();

        $returnRequest = $this->returnService->createShopifyReturn(
            shopDomain: 'lush-clone-internship-project.myshopify.com',
            email: $validated['email'] ?? '',
            reason: $validated['reason'],
            items: $validated['items'],
            shopifyCustomerId: $order->shopify_customer_id,
            shopifyOrderId: $order->shopify_order_id,
            shopifyOrderName: $order->shopify_order_name,
            notes: $validated['notes'] ?? null,
        );

        AuditLog::record(
            module: 'sales',
            action: 'return_requested',
            entity: $returnRequest,
            label: 'Return #'.$returnRequest->id.' ('.$validated['order_id'].')',
            changes: [
                'new' => [
                    'status' => $returnRequest->status,
                    'reason' => $validated['reason'],
                ],
            ],
            description: 'Return requested via Shopify customer account.',
            actorName: $validated['email'] ?? 'Shopify customer',
        );

        return (new ReturnRequestResource($returnRequest))
            ->response()
            ->setStatusCode(201);
    }


    private function proxySecretConfigKeys(): array
    {
        return [
            'sales.shopify.client_secret',
            'sales.shopify.returns_client_secret',
        ];
    }

    public function indexByOrderFromCustomerAccount(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'shopify_order_id' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $orderIdCandidates = $this->shopifyOrderIdCandidates(
            $validated['shopify_order_id']
        );

        $returns = ReturnRequest::query()
            ->whereIn('shopify_order_id', $orderIdCandidates)
            ->latest('created_at')
            ->get();

        return response()->json([
            'data' => $returns->map(
                function (ReturnRequest $returnRequest): array {
                    $items = collect($returnRequest->items ?? []);

                    return [
                        'id' => $returnRequest->id,
                        'order_name' => $returnRequest->shopify_order_name,
                        'status' => $returnRequest->status,
                        'reason' => $returnRequest->reason,
                        'notes' => $returnRequest->notes,

                        'items_count' => $items->sum(
                            fn ($item): int =>
                            (int) ($item['quantity'] ?? 0)
                        ),

                        'items' => $items->values(),

                        'created_at' => $returnRequest
                            ->created_at
                            ?->format('d.m.Y H:i'),
                    ];
                }
            )->values(),
        ]);
    }
}
