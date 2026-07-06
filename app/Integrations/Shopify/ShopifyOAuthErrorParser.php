<?php

namespace App\Integrations\Shopify;

use Illuminate\Http\Client\Response;

class ShopifyOAuthErrorParser
{
    public static function messageFromResponse(Response $response, string $context): string
    {
        $oauthError = self::extractOAuthError($response);

        if ($oauthError !== null) {
            return rtrim("{$context}: {$oauthError}", '.').'.';
        }

        return "{$context} (HTTP {$response->status()}).";
    }

    public static function extractOAuthError(Response $response): ?string
    {
        $body = $response->json();

        if (is_array($body)) {
            $error = $body['error'] ?? null;

            if (is_string($error) && $error !== '') {
                $description = $body['error_description'] ?? null;

                if (is_string($description) && $description !== '' && self::isSafeDescription($description)) {
                    return "{$error} — {$description}";
                }

                return $error;
            }
        }

        if (preg_match('/Oauth error ([a-z_]+)/i', $response->body(), $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private static function isSafeDescription(string $description): bool
    {
        return ! preg_match('/shpat_|client_secret|access_token/i', $description);
    }
}
