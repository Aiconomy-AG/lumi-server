<?php

namespace Modules\Sales\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Modules\Sales\Transformers\ProductResource;

class ProductVariantController extends Controller
{
    public function updateStock(Request $request, int $productId, int $variantId): ProductResource
    {
        $validated = $request->validate([
            'stock_quantity' => ['required', 'integer', 'min:0'],
        ]);

        $product = Product::findOrFail($productId);

        $variant = ProductVariant::where('product_id', $product->id)
            ->where('id', $variantId)
            ->firstOrFail();

        $variant->update([
            'stock_quantity' => $validated['stock_quantity'],
        ]);

        return new ProductResource($product->fresh()->load('variants'));
    }
}
