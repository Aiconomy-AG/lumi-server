<?php

namespace Modules\Sales\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Sales\Models\Customer;
use Modules\Sales\Transformers\CustomerResource;

class CustomerController extends Controller
{
    public function show(Request $request, $customerId)
    {
        if (!Auth::guard('sanctum')->check()) {
            return response()->json(['code' => 'UNAUTHORIZED', 'message' => 'Authentication is required or invalid.'], 401);
        }

        $customer = Customer::find($customerId);

        if (!$customer) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Customer profile not found.'], 404);
        }

        if (Auth::guard('sanctum')->id() !== (int) $customerId) {
            return response()->json(['code' => 'FORBIDDEN', 'message' => 'Access denied.'], 403);
        }

        return new CustomerResource($customer);
    }
}
