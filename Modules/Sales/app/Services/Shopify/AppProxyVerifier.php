<?php

namespace Modules\Sales\Services\Shopify;

use Illuminate\Http\Request;

class AppProxyVerifier
{
    public function verify(Request $request): bool
    {
        $signature = (string) $request->query('signature', '');

        if ($signature === '') {
            return false;
        }

        $parameters = $request->query();
        unset($parameters['signature']);

        $pairs = [];

        foreach ($parameters as $key => $value) {
            $values = is_array($value) ? $value : [$value];
            $pairs[] = $key.'='.implode(',', array_map('strval', $values));
        }

        sort($pairs, SORT_STRING);

        $calculated = hash_hmac(
            'sha256',
            implode('', $pairs),
            (string) config('sales.shopify.client_secret'),
        );

        return hash_equals($calculated, $signature);
    }
}
