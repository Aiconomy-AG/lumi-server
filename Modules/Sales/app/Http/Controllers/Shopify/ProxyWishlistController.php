<?php

namespace Modules\Sales\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\WishlistItem;
use Modules\Sales\Services\Shopify\AppProxyVerifier;
use Modules\Sales\Support\ShopifyId;

class ProxyWishlistController extends Controller
{
    public function __construct(
        private readonly AppProxyVerifier $verifier,
    ) {}

    public function index(Request $request): JsonResponse
    {

        $customer = $this->customerFromProxy($request);

        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $items = WishlistItem::query()
            ->where('customer_id', $customer->id)
            ->with('product')
            ->latest()
            ->get()
            ->map(fn (WishlistItem $item) => [
                'product_id' => $item->product?->shopify_product_id,
                'handle' => $item->product?->sku,
                'title' => $item->product?->name,
            ])
            ->values();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $this->customerFromProxy($request);

        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $validated = $request->validate([
            'shopify_product_id' => ['required', 'string'],
        ]);

        $product = $this->findProduct($validated['shopify_product_id']);

        if (! $product) {
            return response()->json([
                'message' => 'Product is not synced to the backend yet.',
            ], 404);
        }

        WishlistItem::query()->firstOrCreate([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        return response()->json([
            'saved' => true,
            'product_id' => $product->shopify_product_id,
        ], 201);
    }

    public function destroy(Request $request, string $shopifyProductId): JsonResponse
    {
        $customer = $this->customerFromProxy($request);

        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $product = $this->findProduct($shopifyProductId);

        if ($product) {
            WishlistItem::query()
                ->where('customer_id', $customer->id)
                ->where('product_id', $product->id)
                ->delete();
        }

        return response()->json(['saved' => false]);
    }

    private function customerFromProxy(Request $request): Customer|JsonResponse
    {
        if (! $this->verifier->verify($request)) {
            return response()->json(['message' => 'Invalid Shopify proxy signature.'], 401);
        }

        $shopifyCustomerId = ShopifyId::numeric((string) $request->query('logged_in_customer_id', ''));

        if ($shopifyCustomerId === null || $shopifyCustomerId === '') {
            return response()->json([
                'message' => 'Sign in with your store account to use wishlist.',
            ], 401);
        }

        return Customer::query()->firstOrCreate(
            ['shopify_customer_id' => $shopifyCustomerId],
            [
                'username' => 'Shopify Customer '.$shopifyCustomerId,
                'email' => null,
            ],
        );
    }

    private function findProduct(string $shopifyProductId): ?Product
    {
        $gid = ShopifyId::productGid($shopifyProductId);

        return Product::query()
            ->where('shopify_product_id', $shopifyProductId)
            ->when($gid !== null, fn ($query) => $query->orWhere('shopify_product_id', $gid))
            ->first();
    }
}
