<?php

namespace Modules\Sales\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Sales\Integrations\Shopify\ProductSyncService;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Modules\Sales\Transformers\ProductResource;

class ProductVariantController extends Controller
{
    public function __construct(
        private readonly ProductSyncService $shopify,
    ) {}

    public function store(Request $request, int $productId)
    {
        $product = Product::findOrFail($productId);
        $this->authorize('update', $product);
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:255', 'unique:product_variants,sku'],
            'name' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', 'string', 'max:20'],
            'colour' => ['nullable', 'string', 'max:255'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:255'],
        ]);

        $product->variants()->create([
            ...$validated,
            'price' => $validated['price'] ?? $product->price,
            'stock_quantity' => $validated['stock_quantity'] ?? 0,
        ]);

        $product->load(['variants', 'category']);
        $this->shopify->createVariant($product);

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $productId, int $variantId): ProductResource
    {
        $product = Product::findOrFail($productId);
        $this->authorize('update', $product);
        $variant = $this->findVariant($product, $variantId);

        $validated = $request->validate([
            'sku' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('product_variants', 'sku')->ignore($variant->id),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', 'string', 'max:20'],
            'colour' => ['nullable', 'string', 'max:255'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:255'],
        ]);

        $variant->update($validated);

        $product->load(['variants', 'category']);
        $this->shopify->updateVariant($product);

        return new ProductResource($product);
    }

    public function destroy(int $productId, int $variantId)
    {
        $product = Product::findOrFail($productId);
        $this->authorize('delete', $product);
        $variant = $this->findVariant($product, $variantId);

        try {
            $variant->delete();
        } catch (QueryException $exception) {
            if ((int) $exception->getCode() === 23000) {
                return response()->json([
                    'message' => 'Cannot delete variant with related records.',
                ], 409);
            }

            throw $exception;
        }

        $this->shopify->deleteVariant($product->load(['variants', 'category']));

        return response()->noContent();
    }

    public function updateStock(Request $request, int $productId, int $variantId): ProductResource
    {
        $product = Product::findOrFail($productId);
        $this->authorize('updateStock', $product);
        $validated = $request->validate([
            'stock_quantity' => ['required', 'integer', 'min:0'],
        ]);

        $variant = $this->findVariant($product, $variantId);

        $variant->update([
            'stock_quantity' => $validated['stock_quantity'],
        ]);

        $product->load(['variants', 'category']);
        $this->shopify->updateVariant($product);

        return new ProductResource($product);
    }

    private function findVariant(Product $product, int $variantId): ProductVariant
    {
        return ProductVariant::where('product_id', $product->id)
            ->where('id', $variantId)
            ->firstOrFail();
    }
}
