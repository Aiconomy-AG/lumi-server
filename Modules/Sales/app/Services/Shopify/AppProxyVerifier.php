<?php

namespace Modules\Sales\Services\Shopify;

use Illuminate\Http\Request;

class AppProxyVerifier
{
    public function verify(Request $request, array $secretConfigKeys = [
        'sales.shopify.client_secret','sales.shopify.returns_client_secret',
        'sales.shopify.wishlist_secret',
    ]): bool {

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

        logger()->info('SHOPIFY PROXY DEBUG', [
            'query_string' => $queryString,
            'params' => $params,
            'pairs' => $pairs,
            'message' => $message,
            'received_signature' => $signature,
        ]);

        foreach ($secretConfigKeys as $configKey) {
            $secret = trim((string) config($configKey));

            if ($secret === '') {
                logger()->warning('SHOPIFY PROXY EMPTY SECRET', [
                    'config_key' => $configKey,
                ]);

                continue;
            }

            $calculated = hash_hmac('sha256', $message, $secret);
            $matches = hash_equals($calculated, $signature);

            logger()->info('SHOPIFY PROXY SIGNATURE ATTEMPT', [
                'config_key' => $configKey,
                'secret_length' => strlen($secret),
                'secret_fingerprint' => substr(hash('sha256', $secret), 0, 12),
                'received_signature' => $signature,
                'calculated_signature' => $calculated,
                'matches' => $matches,
            ]);

            if ($matches) {
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
