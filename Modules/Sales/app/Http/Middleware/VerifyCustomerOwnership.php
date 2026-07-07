<?php

namespace Modules\Sales\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Sales\Models\Customer;
use Symfony\Component\HttpFoundation\Response;

class VerifyCustomerOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $customer = Customer::resolveFromUser($user);

        if (!$user->isAdmin() && $customer->id !== (int) $request->route('customerId')) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Access denied.',
            ], 403);
        }

        return $next($request);
    }
}
