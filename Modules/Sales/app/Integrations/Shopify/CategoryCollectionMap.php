<?php

namespace Modules\Sales\Integrations\Shopify;

class CategoryCollectionMap
{
    /**
     * @return array<int, string>
     */
    public function handlesByCategoryId(): array
    {
        $mapping = config('sales.shopify.category_collections', []);

        if (! is_array($mapping)) {
            return [];
        }

        $normalized = [];

        foreach ($mapping as $categoryId => $handle) {
            if (! is_numeric($categoryId) || ! is_string($handle) || $handle === '') {
                continue;
            }

            $normalized[(int) $categoryId] = $handle;
        }

        return $normalized;
    }

    public function handleForCategory(int $categoryId): ?string
    {
        return $this->handlesByCategoryId()[$categoryId] ?? null;
    }
}
