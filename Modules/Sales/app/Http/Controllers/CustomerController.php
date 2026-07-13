<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Models\Customer;
use Modules\Sales\Transformers\CustomerResource;

class CustomerController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();

        $customer = Customer::resolveFromUser($user);

        return new CustomerResource($customer);
    }

    public function show(Request $request, $customerId)
    {
        $customer = Customer::find($customerId);

        if (!$customer) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Customer profile not found.'
            ], 404);
        }

        return new CustomerResource($customer);
    }
}
