<?php

namespace Modules\Sales\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyProxySignature
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $providedSignature = $request->query('signature');

        if (
            ! is_string($providedSignature) ||
            $providedSignature === ''
        ) {
            abort(401, 'Missing Shopify proxy signature.');
        }

        $parameters = $request->query();

        unset($parameters['signature']);

        ksort($parameters);

        $message = collect($parameters)
            ->map(function (mixed $value, string $key): string {
                if (is_array($value)) {
                    $value = implode(',', $value);
                }

                return $key . '=' . $value;
            })
            ->implode('');

        $secret = (string) config(
            'sales.shopify.client_secret'
        );

        if ($secret === '') {
            abort(
                500,
                'Shopify client secret is not configured.'
            );
        }

        $calculatedSignature = hash_hmac(
            'sha256',
            $message,
            $secret
        );

        if (
            ! hash_equals(
                $calculatedSignature,
                $providedSignature
            )
        ) {
            abort(401, 'Invalid Shopify proxy signature.');
        }

        return $next($request);
    }
}
