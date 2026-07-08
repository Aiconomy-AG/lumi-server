<?php

namespace Modules\Sales\Integrations\Shopify;

use Illuminate\Support\Facades\Log;
use Modules\Sales\Exceptions\Shopify\ShopifyException;
use Modules\Sales\Exceptions\Shopify\ShopifyThrottledException;
use Modules\Sales\Jobs\AssignShopifyCollectionJob;
use Modules\Sales\Models\Product;

class CollectionAssignService
{
    private const MAX_THROTTLE_RETRIES = 10;

    private const MAX_PRODUCTS_PER_REQUEST = 250;

    private const COLLECTION_BY_HANDLE_QUERY = <<<'GRAPHQL'
    query CollectionByHandle($handle: String!) {
        collectionByHandle(handle: $handle) {
            id
            title
        }
    }
    GRAPHQL;

    private const COLLECTION_ADD_PRODUCTS_MUTATION = <<<'GRAPHQL'
    mutation CollectionAddProducts($id: ID!, $productIds: [ID!]!) {
        collectionAddProducts(id: $id, productIds: $productIds) {
            collection { id title }
            userErrors { field message }
        }
    }
    GRAPHQL;

    /** @var array<string, string> */
    private array $resolvedCollectionIds = [];

    public function __construct(
        private readonly ShopifyConnector $connector,
        private readonly CategoryCollectionMap $categoryCollectionMap,
    ) {}

    /**
     * Dispatch one background job per mapped category that has synced products.
     *
     * @return int Number of categories queued.
     */
    public function queueAssign(): int
    {
        $queued = 0;

        foreach ($this->categoryCollectionMap->handlesByCategoryId() as $categoryId => $handle) {
            $productIds = $this->syncedProductIdsForCategory($categoryId);

            if ($productIds === []) {
                continue;
            }

            AssignShopifyCollectionJob::dispatch($categoryId, $productIds);
            $queued++;
        }

        return $queued;
    }

    /**
     * Assign every synced product with a mapped category to its Shopify collection.
     *
     * @return array{assigned: int, skipped: int, failed: int}
     */
    public function assignAll(): array
    {
        $stats = ['assigned' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($this->categoryCollectionMap->handlesByCategoryId() as $categoryId => $handle) {
            $productIds = $this->syncedProductIdsForCategory($categoryId);

            if ($productIds === []) {
                continue;
            }

            try {
                $assigned = $this->assignProducts($categoryId, $productIds);
                $stats['assigned'] += $assigned;
            } catch (ShopifyException $exception) {
                $stats['failed'] += count($productIds);

                Log::warning('Shopify collection assignment failed', [
                    'category_id' => $categoryId,
                    'handle' => $handle,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @param  array<int, string>  $shopifyProductIds
     * @return int Number of products assigned.
     */
    public function assignProducts(int $categoryId, array $shopifyProductIds): int
    {
        $handle = $this->categoryCollectionMap->handleForCategory($categoryId);

        if ($handle === null) {
            throw new ShopifyException("No Shopify collection handle configured for category {$categoryId}.");
        }

        $collectionId = $this->resolveCollectionId($handle);
        $assigned = 0;

        foreach (array_chunk($shopifyProductIds, self::MAX_PRODUCTS_PER_REQUEST) as $chunk) {
            $this->addProductsToCollection($collectionId, $chunk);
            $assigned += count($chunk);
        }

        return $assigned;
    }

    /**
     * @return array<int, string>
     */
    private function syncedProductIdsForCategory(int $categoryId): array
    {
        return Product::query()
            ->where('category_id', $categoryId)
            ->whereNotNull('shopify_product_id')
            ->orderBy('id')
            ->pluck('shopify_product_id')
            ->all();
    }

    private function resolveCollectionId(string $handle): string
    {
        if (isset($this->resolvedCollectionIds[$handle])) {
            return $this->resolvedCollectionIds[$handle];
        }

        $response = $this->query([
            'query' => self::COLLECTION_BY_HANDLE_QUERY,
            'variables' => ['handle' => $handle],
        ]);

        $collectionId = $response->data['collectionByHandle']['id'] ?? null;

        if (! is_string($collectionId) || $collectionId === '') {
            throw new ShopifyException("Shopify collection not found for handle \"{$handle}\".");
        }

        return $this->resolvedCollectionIds[$handle] = $collectionId;
    }

    /**
     * @param  array<int, string>  $shopifyProductIds
     */
    private function addProductsToCollection(string $collectionId, array $shopifyProductIds): void
    {
        $response = $this->query([
            'query' => self::COLLECTION_ADD_PRODUCTS_MUTATION,
            'variables' => [
                'id' => $collectionId,
                'productIds' => array_values($shopifyProductIds),
            ],
        ]);

        $result = $response->data['collectionAddProducts'] ?? [];

        $this->assertNoUserErrors($result['userErrors'] ?? []);
    }

    private function query(array $payload): ShopifyResponse
    {
        $attempts = 0;

        while (true) {
            try {
                return $this->connector->query($payload);
            } catch (ShopifyThrottledException $exception) {
                if (++$attempts >= self::MAX_THROTTLE_RETRIES) {
                    throw $exception;
                }

                sleep(max(1, $exception->retryAfterSeconds()));
            }
        }
    }

    private function assertNoUserErrors(array $errors): void
    {
        if ($errors === []) {
            return;
        }

        $messages = array_filter(array_map(
            fn ($error) => is_array($error) ? ($error['message'] ?? null) : null,
            $errors,
        ));

        throw new ShopifyException('Shopify rejected the collection assignment: '.implode('; ', $messages));
    }
}
