<?php

namespace App\Integrations\Shopify;

use App\Exceptions\Shopify\ShopifyException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

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
            throw new ShopifyException('Failed to obtain Shopify access token due to a network error.');
        }

        if (! $response->successful()) {
            throw new ShopifyException(sprintf(
                'Failed to obtain Shopify access token (HTTP %d).',
                $response->status(),
            ));
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new ShopifyException('Shopify token response was not valid JSON.');
        }

        $token = $body['access_token'] ?? null;
        $expiresIn = $body['expires_in'] ?? null;

        if (! is_string($token) || $token === '' || ! is_numeric($expiresIn)) {
            throw new ShopifyException('Shopify token response is missing required fields.');
        }

        $ttl = max(1, (int) $expiresIn - 300);

        $this->cache->put($this->cacheKey(), $token, $ttl);

        return $token;
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
