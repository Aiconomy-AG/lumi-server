<?php

namespace App\Integrations\Shopify;

use App\Exceptions\Shopify\ShopifyException;

class ShopifyConfig
{
    public readonly string $shop;

    public readonly string $clientId;

    public readonly string $clientSecret;

    public readonly string $appUrl;

    public readonly string $apiVersion;

    public function __construct()
    {
        $this->shop = (string) config('services.shopify.shop');
        $this->clientId = (string) config('services.shopify.client_id');
        $this->clientSecret = (string) config('services.shopify.client_secret');
        $this->appUrl = (string) config('services.shopify.app_url');
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
            throw new ShopifyException(
                'Shopify credentials are not configured. Set SHOPIFY_ADMIN_ID and SHOPIFY_ADMIN_SECRET.'
            );
        }

        if ($this->shop === '') {
            throw new ShopifyException('Shopify shop is not configured. Set SHOPIFY_SHOP.');
        }

        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.myshopify\.com$/', $this->shop)) {
            throw new ShopifyException(
                'SHOPIFY_SHOP must be a plain .myshopify.com hostname without protocol or path.'
            );
        }

        if ($this->apiVersion === '') {
            throw new ShopifyException('Shopify API version is not configured. Set SHOPIFY_API_VERSION.');
        }
    }
}
