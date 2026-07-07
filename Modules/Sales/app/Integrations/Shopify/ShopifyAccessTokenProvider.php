<?php

namespace Modules\Sales\Integrations\Shopify;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Sales\Exceptions\Shopify\ShopifyException;

class ShopifyAccessTokenProvider
{
    private const int LOCK_SECONDS = 10;

    public function __construct(
        private readonly ShopifyConfig $config,
        private readonly CacheRepository $cache,
    ) {}

    public function getAccessToken(): string
    {
        $cached = $this->cache->get($this->cacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $lock = $this->cache->lock($this->lockKey(), self::LOCK_SECONDS);

        try {
            $lock->block(self::LOCK_SECONDS);

            $cached = $this->cache->get($this->cacheKey());

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            return $this->requestAndCacheToken();
        } finally {
            $lock->release();
        }
    }

    public function invalidate(): void
    {
        $this->cache->forget($this->cacheKey());
    }

    private function requestAndCacheToken(): string
    {
        try {
            $response = Http::asForm()
                ->connectTimeout(10)
                ->timeout(30)
                ->post($this->config->tokenUrl(), [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->config->clientId,
                    'client_secret' => $this->config->clientSecret,
                ]);
        } catch (ConnectionException) {
            throw new ShopifyException('Shopify token network error.');
        }

        if (! $response->successful()) {
            throw new ShopifyException($this->tokenErrorMessage($response));
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new ShopifyException('Invalid token response.');
        }

        $token = $body['access_token'] ?? null;
        $expiresIn = $body['expires_in'] ?? null;

        if (! is_string($token) || $token === '' || ! is_numeric($expiresIn)) {
            throw new ShopifyException('Invalid token response.');
        }

        $this->cache->put($this->cacheKey(), $token, max(1, (int) $expiresIn - 300));

        return $token;
    }

    private function tokenErrorMessage(Response $response): string
    {
        $error = $response->json('error');

        if (is_string($error) && $error !== '') {
            return $error;
        }

        if (preg_match('/Oauth error ([a-z_]+)/i', $response->body(), $matches) === 1) {
            return $matches[1];
        }

        return 'Token request failed (HTTP '.$response->status().').';
    }

    private function cacheKey(): string
    {
        return 'shopify.access_token.'.$this->config->shop;
    }

    private function lockKey(): string
    {
        return 'shopify.access_token.lock.'.$this->config->shop;
    }
}
