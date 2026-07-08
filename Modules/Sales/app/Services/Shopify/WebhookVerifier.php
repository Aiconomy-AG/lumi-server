<?php

namespace Modules\Sales\Services\Shopify;

use Illuminate\Http\Request;

class WebhookVerifier
{
    public function verify(Request $request): bool
    {
        $hmac = (string) $request->header('X-Shopify-Hmac-Sha256', '');

        if ($hmac === '') {
            return false;
        }

        $calculated = base64_encode(hash_hmac(
            'sha256',
            $request->getContent(),
            (string) config('sales.shopify.client_secret'),
            true,
        ));

        return hash_equals($calculated, $hmac);
    }
}
