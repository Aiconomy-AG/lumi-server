<?php

namespace App\Integrations\Shopify;

use App\Exceptions\Shopify\ShopifyException;
use Illuminate\Support\Facades\Http;

class ShopifyConnector
{
    private ?string $accessToken = null;

    private readonly string $shop;

    private readonly string $clientId;

    private readonly string $clientSecret;

    private readonly string $apiVersion;

    public function __construct()
    {
        $this->shop = (string) config('services.shopify.shop');
        $this->clientId = (string) config('services.shopify.client_id');
        $this->clientSecret = (string) config('services.shopify.client_secret');
        $this->apiVersion = (string) config('services.shopify.api_version');

        $this->validateConfig();
    }

    public function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $response = Http::asForm()->post(
            "https://{$this->shop}/admin/oauth/access_token",
            [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        );

        if (! $response->successful()) {
            throw new ShopifyException(
                'Failed to obtain Shopify access token (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new ShopifyException('Shopify token response did not include an access_token.');
        }

        $this->accessToken = $token;

        return $this->accessToken;
    }

    public function testConnection(): ShopifyResponse
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->getAccessToken(),
        ])->get("https://{$this->shop}/admin/api/{$this->apiVersion}/shop.json");

        if (! $response->successful()) {
            throw new ShopifyException(
                'Shopify connection failed (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $shop = $response->json('shop');

        if (! is_array($shop)) {
            throw new ShopifyException('Unexpected response from Shopify shop endpoint.');
        }

        return ShopifyResponse::fromShopData($shop);
    }

    private function validateConfig(): void
    {
        if ($this->shop === '' || $this->clientId === '' || $this->clientSecret === '') {
            throw new ShopifyException(
                'Shopify credentials are not configured. Set SHOPIFY_SHOP, SHOPIFY_ADMIN_ID, and SHOPIFY_ADMIN_SECRET in your .env file.'
            );
        }
    }
}
