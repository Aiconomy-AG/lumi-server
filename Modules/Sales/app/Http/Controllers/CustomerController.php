<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Models\Customer;
use Modules\Sales\Transformers\CustomerResource;

class CustomerController extends Controller
{
    /**
     * Retrieve the current authenticated customer profile (GET /shop/me).
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $customer = Customer::resolveFromUser($user);

        return new CustomerResource($customer);
    }

    /**
     * Get a customer profile by ID (GET /shop/customers/{customerId}).
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

        $user = $request->user();
        $currentCustomer = Customer::resolveFromUser($user);

        // Restrict to profile owner unless the requesting user is an admin
        if (!$user->isAdmin() && $currentCustomer->id !== (int) $customerId) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Access denied.'
            ], 403);
        }

        return new CustomerResource($customer);
    }
}
