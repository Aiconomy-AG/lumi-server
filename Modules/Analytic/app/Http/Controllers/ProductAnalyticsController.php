<?php

namespace Modules\Analytic\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Sales\Models\Product;

class ProductAnalyticsController extends Controller
{
    public function mostWishlisted(): JsonResponse
    {
        $products = Product::query()
            ->withCount([
                'wishedByCustomers as wishlist_count',
            ])
            ->orderByDesc('wishlist_count')
            ->get();

        return response()->json([
            'data' => $products,
        ]);
    }
}
