<?php

namespace Modules\Sales\Services\Shopify;

use Illuminate\Http\Request;

class AppProxyVerifier
{
    public function verify(Request $request): bool
    {
        $secret = (string) config('sales.shopify.client_secret');

        if ($secret === '') {
            return false;
        }

        $queryString = (string) $request->server('QUERY_STRING', '');

        if ($queryString === '') {
            return false;
        }

        parse_str($queryString, $parameters);

        $signature = (string) ($parameters['signature'] ?? '');

        if ($signature === '') {
            return false;
        }

        unset($parameters['signature']);

        $pairs = [];

        foreach ($parameters as $key => $value) {
            $values = is_array($value) ? $value : [$value];

            $pairs[] = $key . '=' . implode(',', array_map('strval', $values));
        }

        sort($pairs, SORT_STRING);

        $message = implode('', $pairs);

        $calculated = hash_hmac('sha256', $message, $secret);

        return hash_equals($calculated, $signature);
    }
}
