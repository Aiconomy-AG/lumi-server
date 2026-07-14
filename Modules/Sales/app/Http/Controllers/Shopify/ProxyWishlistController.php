<?php

namespace Modules\Sales\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\WishlistItem;
use Modules\Sales\Support\ShopifyId;

class ProxyWishlistController extends Controller
{
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
            ->map(fn (WishlistItem $item) => $this->wishlistItemPayload($item))
            ->filter()
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
            'shopify_product_id' => ['nullable', 'string', 'required_without:product_handle'],
            'product_handle' => ['nullable', 'string', 'required_without:shopify_product_id'],
        ]);

        $product = $this->findProduct(
            $validated['shopify_product_id'] ?? $validated['product_handle'] ?? ''
        );

        if (! $product) {
            return response()->json([
                'message' => 'Product is not synced to the backend yet.',
            ], 404);
        }

        $item = WishlistItem::query()->firstOrCreate([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        return response()->json([
            'saved' => true,
            'item' => $this->wishlistItemPayload($item->load('product')),
        ], 201);
    }

    public function destroy(Request $request, string $productIdentifier): JsonResponse
    {
        $customer = $this->customerFromProxy($request);

        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $product = $this->findProduct($productIdentifier);

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

    private function findProduct(string $productIdentifier): ?Product
    {
        $identifier = urldecode($productIdentifier);
        $gid = ShopifyId::productGid($identifier);
        $numeric = ShopifyId::numeric($identifier);

        return Product::query()
            ->where('shopify_product_id', $identifier)
            ->when($gid !== null, fn ($query) => $query->orWhere('shopify_product_id', $gid))
            ->when($numeric !== null, fn ($query) => $query->orWhere('shopify_product_id', $numeric))
            ->orWhere('sku', $identifier)
            ->first();
    }

    private function wishlistItemPayload(WishlistItem $item): ?array
    {
        $product = $item->product;

        if (! $product instanceof Product) {
            return null;
        }

        $shopifyProductId = $product->shopify_product_id;
        $numericProductId = ShopifyId::numeric($shopifyProductId);
        $handle = $product->sku;

        return [
            'id' => $item->id,
            'backend_product_id' => $product->id,
            'shopify_product_id' => $shopifyProductId,
            'numeric_product_id' => $numericProductId,
            'handle' => $handle,
            'title' => $product->name,
            'description' => $product->description,
            'image_url' => $product->image_url,
            'price' => $product->price,
            'product_url' => $handle ? "/products/{$handle}" : null,
            'saved_at' => $item->created_at?->toISOString(),
        ];
    }
}
