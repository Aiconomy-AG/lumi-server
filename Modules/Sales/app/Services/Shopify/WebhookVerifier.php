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

        foreach ($this->secrets() as $secret) {
            $calculated = base64_encode(hash_hmac(
                'sha256',
                $request->getContent(),
                $secret,
                true,
            ));

            if (hash_equals($calculated, $hmac)) {
                return true;
            }
        }

        return false;
    }

    private function secrets(): array
    {
        return collect([
            config('sales.shopify.client_secret'),
            config('sales.shopify.webhook_secret'),
        ])
            ->filter(fn ($secret) => is_string($secret) && $secret !== '')
            ->unique()
            ->values()
            ->all();
    }
}
