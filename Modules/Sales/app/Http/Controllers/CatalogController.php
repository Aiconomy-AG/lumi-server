<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Sales\Models\Category;
use Modules\Sales\Models\Product;
use Modules\Sales\Transformers\CategoryResource;
use Modules\Sales\Transformers\ProductResource;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    // GET /sales/products
    public function index(Request $request)
    {
        $limit = $request->query('limit', 20);

        $query = Product::with(['variants', 'ingredients']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        $products = $query->limit($limit)->get();

        return ProductResource::collection($products);
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
}
