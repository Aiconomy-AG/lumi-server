<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Models\Category;
use Modules\Sales\Models\Ingredients;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Modules\Sales\Transformers\CategoryResource;
use Modules\Sales\Transformers\ProductResource;
use Modules\Sales\Transformers\ProductVariantResource;
use Modules\Sales\Transformers\IngredientResource;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->query('limit', 20);
        $query = Product::with(['variants', 'ingredients']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        return ProductResource::collection($query->limit($limit)->get());
    }

    public function show($productId)
    {
        $product = Product::with(['variants', 'ingredients'])->find($productId);

        if (!$product) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Product not found.'], 404);
        }

        return new ProductResource($product);
    }

    public function categories()
    {
        return CategoryResource::collection(Category::all());
    }

    public function productVariants($productId)
    {
        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Product not found.'], 404);
        }

        return ProductVariantResource::collection($product->variants);
    }

    public function variantDetails($variantId)
    {
        $variant = ProductVariant::find($variantId);
        if (!$variant) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Variant not found.'], 404);
        }

        return new ProductVariantResource($variant);
    }

    public function ingredients(Request $request)
    {
        $query = Ingredients::query();

        // Handle boolean filters from query parameters
        if ($request->has('is_allergen')) {
            $query->where('is_allergen', $request->boolean('is_allergen'));
        }
        if ($request->has('is_vegan')) {
            $query->where('is_vegan', $request->boolean('is_vegan'));
        }
        if ($request->has('is_natural')) {
            $query->where('is_natural', $request->boolean('is_natural'));
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->query('search') . '%');
        }

        return IngredientResource::collection($query->get());
    }

    public function ingredientDetails($ingredientId)
    {
        $ingredient = Ingredients::find($ingredientId);
        if (!$ingredient) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Ingredient not found.'], 404);
        }

        return new IngredientResource($ingredient);
    }

    public function productIngredients($productId)
    {
        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Product not found.'], 404);
        }

        return IngredientResource::collection($product->ingredients);
    }
}
