<?php

namespace Modules\Sales\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Modules\Sales\Models\Order;
use Modules\Sales\Transformers\OrderResource;

class OrderController extends Controller
{
    public function index()
    {
        return OrderResource::collection(
            Order::with(['items', 'customer'])->latest()->paginate(25)
        );
    }
}
