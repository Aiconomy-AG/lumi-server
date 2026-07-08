<?php

namespace Modules\Sales\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\Product;
use Modules\Sales\Transformers\ProductResource;

class ProductController extends Controller
{
    public function index()
    {
        return ProductResource::collection(Product::with('variants')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:255'],
            'variants' => ['sometimes', 'array', 'min:1'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:255', 'unique:product_variants,sku'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.weight' => ['nullable', 'numeric', 'min:0'],
            'variants.*.weight_unit' => ['nullable', 'string', 'max:20'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        $productData = collect($validated)->except(['variants'])->all();
        $variantData = $validated['variants'] ?? [];

        $product = DB::transaction(function () use ($productData, $variantData): Product {
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

        return (new ProductResource($product->load('variants')))
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
            'sku' => ['nullable', 'string', 'max:255'],
        ]);

        $product->update($validated);

        return new ProductResource($product->load('variants'));
    }

    public function destroy(int $productId)
    {
        $product = Product::findOrFail($productId);

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
