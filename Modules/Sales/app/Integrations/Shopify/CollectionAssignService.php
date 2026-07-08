<?php

namespace Modules\Sales\Integrations\Shopify;

use Illuminate\Support\Facades\Log;
use Modules\Sales\Exceptions\Shopify\ShopifyException;
use Modules\Sales\Exceptions\Shopify\ShopifyThrottledException;
use Modules\Sales\Jobs\AssignShopifyCollectionJob;
use Modules\Sales\Models\Category;
use Modules\Sales\Models\Product;
use Throwable;

class CollectionAssignService
{
    private const MAX_THROTTLE_RETRIES = 10;

    private const MAX_PRODUCTS_PER_REQUEST = 250;

    private const COLLECTION_BY_HANDLE_QUERY = <<<'GRAPHQL'
    query CollectionByHandle($handle: String!) {
        collectionByHandle(handle: $handle) {
            id
            handle
            title
        }
    }
    GRAPHQL;

    private const COLLECTION_CREATE_MUTATION = <<<'GRAPHQL'
    mutation CollectionCreate($input: CollectionInput!) {
        collectionCreate(input: $input) {
            collection { id handle title }
            userErrors { field message }
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

    private const PUBLICATIONS_QUERY = <<<'GRAPHQL'
    query Publications {
        publications(first: 50) {
            edges { node { id name } }
        }
    }
    GRAPHQL;

    private const ONLINE_STORE_PUBLICATIONS_QUERY = <<<'GRAPHQL'
    query OnlineStorePublications {
        publications(first: 5, catalogType: ONLINE_STORE) {
            edges { node { id name } }
        }
    }
    GRAPHQL;

    private const PUBLISHABLE_PUBLISH_MUTATION = <<<'GRAPHQL'
    mutation PublishablePublish($id: ID!, $input: [PublicationInput!]!) {
        publishablePublish(id: $id, input: $input) {
            publishable { __typename }
            userErrors { field message }
        }
    }
    GRAPHQL;

    /** @var array<string, string> */
    private array $resolvedCollectionIds = [];

    private ?string $onlineStorePublicationId = null;

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

        $collectionId = $this->resolveCollectionId($categoryId, $handle);
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

    private function resolveCollectionId(int $categoryId, string $handle): string
    {
        if (isset($this->resolvedCollectionIds[$handle])) {
            return $this->resolvedCollectionIds[$handle];
        }

        $category = null;

        try {
            $category = Category::query()->find($categoryId);
        } catch (Throwable) {
            // Isolated unit tests may exercise this service before migrations.
        }

        if ($category?->shopify_collection_id) {
            return $this->resolvedCollectionIds[$handle] = $category->shopify_collection_id;
        }

        $response = $this->query([
            'query' => self::COLLECTION_BY_HANDLE_QUERY,
            'variables' => ['handle' => $handle],
        ]);

        $collectionId = $response->data['collectionByHandle']['id'] ?? null;
        $resolvedHandle = $response->data['collectionByHandle']['handle'] ?? $handle;

        if (! is_string($collectionId) || $collectionId === '') {
            $created = $this->createCollection($category, $handle);
            $collectionId = $created['id'];
            $resolvedHandle = $created['handle'];
            $this->publishCollection($collectionId);
        }

        if ($category) {
            $category->forceFill([
                'shopify_collection_id' => $collectionId,
                'shopify_collection_handle' => $resolvedHandle,
            ])->save();
        }

        return $this->resolvedCollectionIds[$handle] = $collectionId;
    }

    /**
     * @return array{id: string, handle: string}
     */
    private function createCollection(?Category $category, string $handle): array
    {
        $title = $category?->name ?: str($handle)->replace('-', ' ')->title()->toString();

        $response = $this->query([
            'query' => self::COLLECTION_CREATE_MUTATION,
            'variables' => [
                'input' => [
                    'title' => $title,
                    'handle' => $handle,
                ],
            ],
        ]);

        $result = $response->data['collectionCreate'] ?? [];
        $this->assertNoUserErrors($result['userErrors'] ?? []);

        $collectionId = $result['collection']['id'] ?? null;
        $collectionHandle = $result['collection']['handle'] ?? $handle;

        if (! is_string($collectionId) || $collectionId === '') {
            throw new ShopifyException("Shopify did not return a collection id for handle \"{$handle}\".");
        }

        return [
            'id' => $collectionId,
            'handle' => is_string($collectionHandle) && $collectionHandle !== '' ? $collectionHandle : $handle,
        ];
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

        $this->assertNoUserErrors($this->withoutAlreadyAssignedErrors($result['userErrors'] ?? []));
    }

    private function publishCollection(string $collectionId): void
    {
        if (! (bool) config('sales.shopify.publish_products', true)) {
            return;
        }

        try {
            $publicationId = $this->onlineStorePublicationId();

            if ($publicationId === null) {
                Log::warning('Shopify collection publish skipped: Online Store publication not found');

                return;
            }

            $response = $this->query([
                'query' => self::PUBLISHABLE_PUBLISH_MUTATION,
                'variables' => [
                    'id' => $collectionId,
                    'input' => [['publicationId' => $publicationId]],
                ],
            ]);

            $result = $response->data['publishablePublish'] ?? [];
            $this->assertNoUserErrors($this->withoutAlreadyAssignedErrors($result['userErrors'] ?? []));
        } catch (ShopifyException $exception) {
            Log::warning('Shopify collection publish failed', [
                'collection_id' => $collectionId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function onlineStorePublicationId(): ?string
    {
        if ($this->onlineStorePublicationId !== null) {
            return $this->onlineStorePublicationId;
        }

        $configured = config('sales.shopify.online_store_publication_id');

        if (is_string($configured)) {
            $configured = trim($configured);

            if ($configured !== '') {
                return $this->onlineStorePublicationId = $configured;
            }
        }

        $onlineStoreNodes = $this->publicationNodes(self::ONLINE_STORE_PUBLICATIONS_QUERY);

        if ($onlineStoreNodes !== []) {
            return $this->onlineStorePublicationId = (string) $onlineStoreNodes[0]['id'];
        }

        $allNodes = $this->publicationNodes(self::PUBLICATIONS_QUERY);

        foreach ($allNodes as $node) {
            $name = strtolower(trim((string) ($node['name'] ?? '')));

            if ($name === 'online store' || str_contains($name, 'online store')) {
                return $this->onlineStorePublicationId = (string) $node['id'];
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publicationNodes(string $query): array
    {
        $response = $this->query(['query' => $query]);
        $edges = $response->data['publications']['edges'] ?? [];
        $nodes = [];

        foreach ($edges as $edge) {
            $node = is_array($edge) ? ($edge['node'] ?? null) : null;

            if (is_array($node) && isset($node['id'])) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @param  array<int, mixed>  $errors
     * @return array<int, mixed>
     */
    private function withoutAlreadyAssignedErrors(array $errors): array
    {
        return array_values(array_filter($errors, function ($error) {
            $message = is_array($error) ? strtolower((string) ($error['message'] ?? '')) : '';

            return ! str_contains($message, 'already');
        }));
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
