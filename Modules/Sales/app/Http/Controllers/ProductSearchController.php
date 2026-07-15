<?php

namespace Modules\Sales\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;

class ProductSearchController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],

            'category_id' => ['nullable', 'integer'],
            'ingredient_ids' => ['nullable', 'array'],
            'ingredient_ids.*' => ['integer'],

            'is_vegan' => ['nullable', 'boolean'],
            'has_allergens' => ['nullable', 'boolean'],
            'is_all_natural' => ['nullable', 'boolean'],
            'available' => ['nullable', 'boolean'],

            'colour' => ['nullable', 'string', 'max:100'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', 'string', 'max:20'],

            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => [
                'nullable',
                'numeric',
                'min:0',
                'gte:min_price',
            ],

            'sort' => ['nullable', 'in:price_asc,price_desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search = $this->buildSearch($validated);

        $products = $search->paginate($validated['per_page'] ?? 20);

        $products->setCollection(
            $products->getCollection()->map(
                fn (Product $product): array => $this->mapProduct($product)
            )
        );

        $facetSearch = $this->buildSearch($validated)
            ->options([
                'facets' => $this->facetAttributes(),
                'limit' => 0,
            ]);

        $rawFacets = $facetSearch->raw();

        $response = $products->toArray();

        $response['facets'] = $this->normalizeFacets(
            $rawFacets['facetDistribution'] ?? []
        );

        return response()->json($response);
    }

    private function buildSearch(array $validated)
    {
        $search = Product::search($validated['q'] ?? '')
            ->query(function ($builder): void {
                $builder->with([
                    'variants',
                    'category',
                    'ingredients',
                ]);
            });

        if (isset($validated['category_id'])) {
            $search->where(
                'category_id',
                '=',
                (int) $validated['category_id']
            );
        }

        foreach ($validated['ingredient_ids'] ?? [] as $ingredientId) {
            $search->where(
                'ingredient_ids',
                '=',
                (int) $ingredientId
            );
        }

        if (isset($validated['is_vegan'])) {
            $search->where(
                'is_vegan',
                '=',
                (bool) $validated['is_vegan']
            );
        }

        if (isset($validated['has_allergens'])) {
            $search->where(
                'has_allergens',
                '=',
                (bool) $validated['has_allergens']
            );
        }

        if (isset($validated['is_all_natural'])) {
            $search->where(
                'is_all_natural',
                '=',
                (bool) $validated['is_all_natural']
            );
        }

        if (($validated['available'] ?? false) === true) {
            $search->where(
                'is_available',
                '=',
                true
            );
        }

        if (isset($validated['colour'])) {
            $search->where(
                'variant_colours',
                '=',
                trim($validated['colour'])
            );
        }

        if (isset($validated['weight'])) {
            $search->where(
                'variant_weights',
                '=',
                (float) $validated['weight']
            );
        }

        if (isset($validated['weight_unit'])) {
            $search->where(
                'variant_weight_units',
                '=',
                $validated['weight_unit']
            );
        }

        if (isset($validated['min_price'])) {
            $search->where(
                'price',
                '>=',
                (float) $validated['min_price']
            );
        }

        if (isset($validated['max_price'])) {
            $search->where(
                'price',
                '<=',
                (float) $validated['max_price']
            );
        }

        match ($validated['sort'] ?? null) {
            'price_asc' => $search->orderBy('price', 'asc'),
            'price_desc' => $search->orderBy('price', 'desc'),
            default => null,
        };

        return $search;
    }

    private function facetAttributes(): array
    {
        return [
            'category_id',
            'ingredient_ids',
            'is_vegan',
            'has_allergens',
            'is_all_natural',
            'is_available',
            'variant_colours',
            'variant_weights',
            'variant_weight_units',
        ];
    }

    private function normalizeFacets(array $facets): array
    {
        return [
            'category_id' => $facets['category_id'] ?? [],
            'ingredient_ids' => $facets['ingredient_ids'] ?? [],

            'available' => [
                'true' => $facets['is_available']['true'] ?? 0,
                'false' => $facets['is_available']['false'] ?? 0,
            ],

            'is_vegan' => [
                'true' => $facets['is_vegan']['true'] ?? 0,
                'false' => $facets['is_vegan']['false'] ?? 0,
            ],

            'has_allergens' => [
                'true' => $facets['has_allergens']['true'] ?? 0,
                'false' => $facets['has_allergens']['false'] ?? 0,
            ],

            'is_all_natural' => [
                'true' => $facets['is_all_natural']['true'] ?? 0,
                'false' => $facets['is_all_natural']['false'] ?? 0,
            ],

            'variant_colours' => $facets['variant_colours'] ?? [],
            'variant_weights' => $facets['variant_weights'] ?? [],
            'variant_weight_units' => $facets['variant_weight_units'] ?? [],
        ];
    }

    private function mapProduct(Product $product): array
    {
        return [
            'id' => (int) $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'description' => $product->description,
            'price' => (float) $product->price,
            'image_url' => $product->image_url,

            'category_id' => $product->category_id !== null
                ? (int) $product->category_id
                : null,

            'category_name' => $product->category?->name,

            'ingredient_ids' => $product->ingredients
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all(),

            'ingredient_names' => $product->ingredients
                ->pluck('name')
                ->filter()
                ->values()
                ->all(),

            'is_vegan' => $product->ingredients->isNotEmpty()
                && $product->ingredients->every(
                    fn ($ingredient): bool => (bool) $ingredient->is_vegan
                ),

            'has_allergens' => $product->ingredients->contains(
                fn ($ingredient): bool => (bool) $ingredient->is_allergen
            ),

            'is_all_natural' => $product->ingredients->isNotEmpty()
                && $product->ingredients->every(
                    fn ($ingredient): bool => (bool) $ingredient->is_natural
                ),

            'is_available' => $product->variants->contains(
                fn (ProductVariant $variant): bool =>
                    (int) $variant->stock_quantity > 0
            ),

            'total_stock' => (int) $product->variants->sum(
                fn (ProductVariant $variant): int =>
                (int) $variant->stock_quantity
            ),

            'variants' => $product->variants
                ->map(function (ProductVariant $variant): array {
                    return [
                        'id' => (int) $variant->id,
                        'shopify_variant_id' => $variant->shopify_variant_id,
                        'sku' => $variant->sku,
                        'name' => $variant->name,
                        'price' => (float) $variant->price,
                        'weight' => $variant->weight !== null
                            ? (float) $variant->weight
                            : null,
                        'weight_unit' => $variant->weight_unit,
                        'colour' => $variant->colour,
                        'options' => $variant->options,
                        'stock_quantity' => (int) $variant->stock_quantity,
                    ];
                })
                ->values()
                ->all(),
        ];
    }
}
