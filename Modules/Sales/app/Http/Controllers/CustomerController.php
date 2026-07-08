<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Models\Customer;
use Modules\Sales\Transformers\CustomerResource;

class CustomerController extends Controller
{
    /**
     * Retrieve the current authenticated customer profile (GET /v1/shop/me).
     */
    public function me(Request $request)
    {
        $user = $request->user();

        $customer = Customer::resolveFromUser($user);

        return new CustomerResource($customer);
    }

    /**
     * Get a customer profile by ID (GET /v1/shop/customers/{customerId}).
     * * Note: Ownership / Admin validation is already handled at the routing layer
     * via the VerifyCustomerOwnership middleware.
     */
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
