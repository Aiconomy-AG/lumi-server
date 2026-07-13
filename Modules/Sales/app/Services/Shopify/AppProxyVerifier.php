<?php

namespace Modules\Sales\Services\Shopify;

use Illuminate\Http\Request;

class AppProxyVerifier
{
    public function verify(Request $request, array $secretConfigKeys = [
        'sales.shopify.client_secret','sales.shopify.returns_client_secret',
        'sales.shopify.wishlist_secret',
    ]): bool {
        logger()->info('Wishlist proxy debug', [
            'query_string' => $request->server('QUERY_STRING'),
            'has_signature' => $request->has('signature'),
            'shop' => $request->query('shop'),
            'path_prefix' => $request->query('path_prefix'),
            'customer_id' => $request->query('logged_in_customer_id'),
            'wishlist_secret_loaded' => filled(
                config('sales.shopify.wishlist_secret')
            ),
        ]);

        $queryString = (string) $request->server('QUERY_STRING', '');

        if ($queryString === '') {
            return false;
        }

        $signature = null;
        $params = [];

        foreach (explode('&', $queryString) as $part) {
            if ($part === '') {
                continue;
            }

            [$rawKey, $rawValue] = array_pad(explode('=', $part, 2), 2, '');

            $key = urldecode($rawKey);
            $value = urldecode($rawValue);

            if ($key === 'signature') {
                $signature = $value;
                continue;
            }

            $params[$key][] = $value;
        }

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        ksort($params, SORT_STRING);

        $pairs = [];

        foreach ($params as $key => $values) {
            $pairs[] = $key . '=' . implode(',', array_map('strval', $values));
        }

        $message = implode('', $pairs);

        foreach ($this->secrets($secretConfigKeys) as $secret) {
            $calculated = hash_hmac('sha256', $message, $secret);

            if (hash_equals($calculated, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function secrets(array $secretConfigKeys): array
    {
        $secrets = [];

        foreach ($secretConfigKeys as $configKey) {
            $secret = (string) config($configKey);

            if ($secret !== '') {
                $secrets[] = $secret;
            }
        }

        return array_values(array_unique($secrets));
    }
}
