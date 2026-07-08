<?php

namespace Modules\Sales\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Integrations\Shopify\ProductSyncService;
use Illuminate\Validation\Rule;
use Modules\Sales\Enums\ShopifySyncStatus;
use Modules\Sales\Models\Category;
use Modules\Sales\Models\Product;
use Modules\Sales\Transformers\ProductResource;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductSyncService $shopify,
    ) {}
    
    private const LOW_STOCK_THRESHOLD = 5;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'stock_status' => ['nullable', Rule::in(['in_stock', 'low_stock', 'out_of_stock'])],
            'shopify_sync_status' => ['nullable', Rule::enum(ShopifySyncStatus::class)],
            'sort_by' => ['nullable', Rule::in(['name', 'price', 'created_at'])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Product::with(['variants', 'category']);

        if (($search = $validated['search'] ?? null) !== null) {
            $query->where(function ($q) use ($search): void {
                $like = '%'.$search.'%';
                $q->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhereHas('variants', function ($v) use ($like): void {
                        $v->where('sku', 'like', $like)->orWhere('name', 'like', $like);
                    });
            });
        }

        if (($name = $validated['name'] ?? null) !== null) {
            $query->where('name', 'like', '%'.$name.'%');
        }

        if (($sku = $validated['sku'] ?? null) !== null) {
            $query->where(function ($q) use ($sku): void {
                $like = '%'.$sku.'%';
                $q->where('sku', 'like', $like)
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like));
            });
        }

        if (isset($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (isset($validated['price_min'])) {
            $query->where('price', '>=', $validated['price_min']);
        }

        if (isset($validated['price_max'])) {
            $query->where('price', '<=', $validated['price_max']);
        }

        if (($stockStatus = $validated['stock_status'] ?? null) !== null) {
            $query->whereHas('variants', function ($v) use ($stockStatus): void {
                match ($stockStatus) {
                    'in_stock' => $v->where('stock_quantity', '>', 0),
                    'low_stock' => $v->whereBetween('stock_quantity', [1, self::LOW_STOCK_THRESHOLD]),
                    'out_of_stock' => $v->where('stock_quantity', 0),
                };
            });
        }

        if (($syncStatus = $validated['shopify_sync_status'] ?? null) !== null) {
            $query->where('shopify_sync_status', $syncStatus);
        }

        $query->orderBy(
            $validated['sort_by'] ?? 'name',
            $validated['sort_dir'] ?? 'asc'
        );

        return ProductResource::collection(
            $query->paginate($validated['per_page'] ?? 25)->appends($request->query())
        );
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
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'variants' => ['sometimes', 'array', 'min:1'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:255', 'unique:product_variants,sku'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.weight' => ['nullable', 'numeric', 'min:0'],
            'variants.*.weight_unit' => ['nullable', 'string', 'max:20'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'variants.*.options' => ['nullable', 'array'],
            'variants.*.options.*' => ['string', 'max:255'],
        ]);

        $productData = collect($validated)->except(['variants', 'category_name', 'stock_quantity'])->all();
        $variantData = $validated['variants'] ?? [];
        $stockQuantity = $validated['stock_quantity'] ?? 0;

        $product = DB::transaction(function () use ($productData, $variantData, $stockQuantity, $validated): Product {
            if (! isset($productData['category_id']) && ! empty($validated['category_name'])) {
                $category = Category::firstOrCreate(['name' => $validated['category_name']]);
                $productData['category_id'] = $category->id;
            }

            $product = Product::create($productData);

            if ($variantData !== []) {
                $product->variants()->createMany(array_map(
                    static fn (array $variant): array => [
                        'sku' => $variant['sku'],
                        'name' => $variant['name'] ?? null,
                        'price' => $variant['price'] ?? $product->price,
                        'weight' => $variant['weight'] ?? null,
                        'weight_unit' => $variant['weight_unit'] ?? null,
                        'stock_quantity' => $variant['stock_quantity'] ?? 0,
                        'options' => $variant['options'] ?? null,
                    ],
                    $variantData
                ));
            } else {
                $this->createDefaultVariant($product, $stockQuantity);
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

        $previousCategoryId = $product->category_id !== null ? (int) $product->category_id : null;

        $product->update($validated);

        $product->load(['variants', 'category']);
        $this->shopify->update($product, $previousCategoryId);

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

    private function createDefaultVariant(Product $product, int $stockQuantity = 0): void
    {
        $sku = $product->sku !== null && $product->sku !== ''
            ? $product->sku
            : 'product-'.$product->getKey();

        $product->variants()->create([
            'sku' => $sku,
            'name' => $product->name,
            'price' => $product->price,
            'stock_quantity' => $stockQuantity,
        ]);
    }
}
