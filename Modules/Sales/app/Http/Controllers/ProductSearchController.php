<?php

namespace Modules\Sales\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Models\Product;

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

        $search = Product::search($validated['q'] ?? '');

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
            $search->where('is_available', '=', true);
        }

        if (isset($validated['colour'])) {
            $search->where(
                'variant_colours',
                '=',
                $validated['colour']
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

        return response()->json(
            $search->paginate($validated['per_page'] ?? 20)
        );
    }
}
