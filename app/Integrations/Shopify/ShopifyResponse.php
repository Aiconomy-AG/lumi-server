<?php

namespace App\Integrations\Shopify;

class ShopifyResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $domain,
        public readonly string $email,
    ) {}

    /**
     * @param  array<string, mixed>  $shop
     */
    public static function fromShopData(array $shop): self
    {
        return new self(
            id: (int) $shop['id'],
            name: (string) $shop['name'],
            domain: (string) ($shop['domain'] ?? $shop['myshopify_domain'] ?? ''),
            email: (string) ($shop['email'] ?? ''),
        );
    }
}
