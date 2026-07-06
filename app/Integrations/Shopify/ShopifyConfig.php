<?php

namespace App\Integrations\Shopify;

use App\Exceptions\Shopify\ShopifyException;

class ShopifyConfig
{
    public readonly string $shop;

    public readonly string $clientId;

    public readonly string $clientSecret;

    public readonly string $apiVersion;

    public function __construct()
    {
        $this->shop = (string) config('services.shopify.shop');
        $this->clientId = (string) config('services.shopify.client_id');
        $this->clientSecret = (string) config('services.shopify.client_secret');
        $this->apiVersion = (string) config('services.shopify.api_version');

        $this->validate();
    }

    public function tokenUrl(): string
    {
        return "https://{$this->shop}/admin/oauth/access_token";
    }

    public function graphqlUrl(): string
    {
        return "https://{$this->shop}/admin/api/{$this->apiVersion}/graphql.json";
    }

    private function validate(): void
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new ShopifyException('Missing Shopify credentials.');
        }

        if ($this->shop === '') {
            throw new ShopifyException('Missing SHOPIFY_SHOP.');
        }

        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.myshopify\.com$/', $this->shop)) {
            throw new ShopifyException('Invalid SHOPIFY_SHOP format.');
        }

        if ($this->apiVersion === '') {
            throw new ShopifyException('Missing SHOPIFY_API_VERSION.');
        }
    }
}
