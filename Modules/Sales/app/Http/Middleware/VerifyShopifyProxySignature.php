<?php

namespace Modules\Sales\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Sales\Services\Shopify\AppProxyVerifier;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyProxySignature
{
    public function __construct(
        private readonly AppProxyVerifier $verifier,
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        if (
            (string) config('sales.shopify.client_secret') === '' &&
            (string) config('sales.shopify.returns_client_secret') === '' &&
            (string) config('sales.shopify.wishlist_secret') === ''
        ) {
            abort(
                500,
                'Shopify proxy secret is not configured.'
            );
        }

        if (! $this->verifier->verify($request)) {
            abort(401, 'Invalid Shopify proxy signature.');
        }

        return $next($request);
    }
}
