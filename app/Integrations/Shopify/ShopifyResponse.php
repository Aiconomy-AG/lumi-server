<?php

namespace App\Integrations\Shopify;

class ShopifyResponse
{
    public function __construct(
        public readonly ?array $data,
        public readonly array $extensions = [],
    ) {}

    public function cost(): ?array
    {
        $cost = $this->extensions['cost'] ?? null;

        return is_array($cost) ? $cost : null;
    }

    public function throttleStatus(): ?array
    {
        $cost = $this->cost();

        if ($cost === null) {
            return null;
        }

        $status = $cost['throttleStatus'] ?? null;

        return is_array($status) ? $status : null;
    }
}
