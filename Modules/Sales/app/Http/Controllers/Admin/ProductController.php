<?php

namespace Modules\Sales\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Integrations\Shopify\ProductSyncService;
use Modules\Sales\Models\Category;
use Modules\Sales\Models\Product;
use Modules\Sales\Transformers\ProductResource;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductSyncService $shopify,
    ) {}
    public function index()
    {
        return ProductResource::collection(Product::with(['variants', 'category'])->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'variants' => ['sometimes', 'array', 'min:1'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:255', 'unique:product_variants,sku'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.weight' => ['nullable', 'numeric', 'min:0'],
            'variants.*.weight_unit' => ['nullable', 'string', 'max:20'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        $productData = collect($validated)->except(['variants', 'category_name'])->all();
        $variantData = $validated['variants'] ?? [];

        $product = DB::transaction(function () use ($productData, $variantData, $validated): Product {
            if (! isset($productData['category_id']) && ! empty($validated['category_name'])) {
                $category = Category::firstOrCreate(['name' => $validated['category_name']]);
                $productData['category_id'] = $category->id;
            }

            $product = Product::create($productData);

            if ($variantData !== []) {
                $product->variants()->createMany(array_map(
                    static fn (array $variant): array => [
                        'sku' => $variant['sku'],
                        'price' => $variant['price'] ?? $product->price,
                        'weight' => $variant['weight'] ?? null,
                        'weight_unit' => $variant['weight_unit'] ?? null,
                        'stock_quantity' => $variant['stock_quantity'] ?? 0,
                    ],
                    $variantData
                ));
            }

            return $product;
        });

        $product->load(['variants', 'category']);
        $this->shopify->create($product);

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $productId): ProductResource
    {
        $product = Product::findOrFail($productId);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
        ]);

        if (! isset($validated['category_id']) && ! empty($validated['category_name'])) {
            $category = Category::firstOrCreate(['name' => $validated['category_name']]);
            $validated['category_id'] = $category->id;
        }

        unset($validated['category_name']);
        $product->update($validated);

        $product->load(['variants', 'category']);
        $this->shopify->update($product);

        return new ProductResource($product);
    }

    public function destroy(int $productId)
    {
        $product = Product::findOrFail($productId);

        $this->shopify->queueDelete($product);

        try {
            $product->delete();
        } catch (QueryException $exception) {
            if ((int) $exception->getCode() === 23000) {
                return response()->json([
                    'message' => 'Cannot delete product with related records.',
                ], 409);
            }

            throw $exception;
        }

        return response()->noContent();
    }
}
