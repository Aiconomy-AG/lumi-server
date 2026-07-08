<?php

namespace Modules\Sales\Integrations\Shopify;

use Illuminate\Support\Str;
use Modules\Sales\Models\Category;
use Throwable;

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

        try {
            Category::query()
                ->select(['id', 'name', 'shopify_collection_handle'])
                ->orderBy('id')
                ->get()
                ->each(function (Category $category) use (&$normalized): void {
                    $normalized[$category->id] = $category->shopify_collection_handle
                        ?: $this->handleFromName($category->name);
                });
        } catch (Throwable) {
            // Database may not be migrated in isolated unit tests.
        }

        return $normalized;
    }

    public function handleForCategory(int $categoryId): ?string
    {
        return $this->handlesByCategoryId()[$categoryId] ?? null;
    }

    private function handleFromName(string $name): string
    {
        $handle = Str::slug($name);

        return $handle !== '' ? $handle : 'category';
    }
}
