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

    private const COLLECTION_REMOVE_PRODUCTS_MUTATION = <<<'GRAPHQL'
    mutation CollectionRemoveProducts($id: ID!, $productIds: [ID!]!) {
        collectionRemoveProducts(id: $id, productIds: $productIds) {
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
     * Dispatch one background job per mapped category to reconcile Shopify
     * collection membership (read-only against the local database).
     *
     * @return int Number of categories queued.
     */
    public function queueAssign(): int
    {
        $queued = 0;

        foreach ($this->categoryCollectionMap->handlesByCategoryId() as $categoryId => $handle) {
            AssignShopifyCollectionJob::dispatch((int) $categoryId);
            $queued++;
        }

        return $queued;
    }

    /**
     * Reconcile every mapped Shopify collection from local product categories.
     * Products are added to their database category collection and removed from
     * every other mapped collection. Does not write to the local database.
     *
     * @return array{assigned: int, removed: int, failed: int}
     */
    public function reconcileAll(): array
    {
        [$allSynced, $byCategory] = $this->syncedProductsByCategory();

        $stats = ['assigned' => 0, 'removed' => 0, 'failed' => 0];

        foreach ($this->categoryCollectionMap->handlesByCategoryId() as $categoryId => $handle) {
            try {
                $result = $this->reconcileCategory((int) $categoryId, $allSynced, $byCategory);
                $stats['assigned'] += $result['assigned'];
                $stats['removed'] += $result['removed'];
            } catch (ShopifyException $exception) {
                $stats['failed']++;

                Log::warning('Shopify collection reconciliation failed', [
                    'category_id' => $categoryId,
                    'handle' => $handle,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Reconcile one mapped category collection. Read-only against the database.
     *
     * @param  array<int, string>  $allSynced
     * @param  array<int, array<int, string>>  $byCategory
     * @return array{assigned: int, removed: int}
     */
    public function reconcileCategoryById(int $categoryId): array
    {
        [$allSynced, $byCategory] = $this->syncedProductsByCategory();

        return $this->reconcileCategory($categoryId, $allSynced, $byCategory);
    }

    /**
     * @deprecated Use reconcileAll() for collection backfills.
     *
     * @return array{assigned: int, skipped: int, failed: int, removed: int}
     */
    public function assignAll(): array
    {
        $stats = $this->reconcileAll();

        return [
            'assigned' => $stats['assigned'],
            'removed' => $stats['removed'],
            'skipped' => 0,
            'failed' => $stats['failed'],
        ];
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
     * @param  array<int, string>  $shopifyProductIds
     * @return int Number of products removed.
     */
    public function removeProducts(int $categoryId, array $shopifyProductIds): int
    {
        if ($shopifyProductIds === []) {
            return 0;
        }

        $handle = $this->categoryCollectionMap->handleForCategory($categoryId);

        if ($handle === null) {
            throw new ShopifyException("No Shopify collection handle configured for category {$categoryId}.");
        }

        $collectionId = $this->lookupCollectionId($categoryId, $handle);

        if ($collectionId === null) {
            return 0;
        }

        $removed = 0;

        foreach (array_chunk($shopifyProductIds, self::MAX_PRODUCTS_PER_REQUEST) as $chunk) {
            $this->removeProductsFromCollection($collectionId, $chunk);
            $removed += count($chunk);
        }

        return $removed;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
     */
    private function syncedProductsByCategory(): array
    {
        $byCategory = [];
        $allSynced = [];

        Product::query()
            ->whereNotNull('shopify_product_id')
            ->where('shopify_product_id', '!=', '')
            ->select(['id', 'category_id', 'shopify_product_id'])
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$byCategory, &$allSynced): void {
                foreach ($products as $product) {
                    $shopifyProductId = (string) $product->shopify_product_id;
                    $allSynced[] = $shopifyProductId;

                    if ($product->category_id !== null) {
                        $byCategory[(int) $product->category_id][] = $shopifyProductId;
                    }
                }
            });

        return [
            array_values(array_unique($allSynced)),
            array_map(
                static fn (array $ids): array => array_values(array_unique($ids)),
                $byCategory,
            ),
        ];
    }

    /**
     * @param  array<int, string>  $allSynced
     * @param  array<int, array<int, string>>  $byCategory
     * @return array{assigned: int, removed: int}
     */
    private function reconcileCategory(int $categoryId, array $allSynced, array $byCategory): array
    {
        $handle = $this->categoryCollectionMap->handleForCategory($categoryId);

        if ($handle === null) {
            throw new ShopifyException("No Shopify collection handle configured for category {$categoryId}.");
        }

        $collectionId = $this->lookupCollectionId($categoryId, $handle);

        if ($collectionId === null) {
            throw new ShopifyException("Shopify collection was not found for handle \"{$handle}\".");
        }

        $shouldBeHere = $byCategory[$categoryId] ?? [];
        $shouldNotBeHere = array_values(array_diff($allSynced, $shouldBeHere));
        $stats = ['assigned' => 0, 'removed' => 0];

        foreach (array_chunk($shouldNotBeHere, self::MAX_PRODUCTS_PER_REQUEST) as $chunk) {
            $this->removeProductsFromCollection($collectionId, $chunk);
            $stats['removed'] += count($chunk);
        }

        foreach (array_chunk($shouldBeHere, self::MAX_PRODUCTS_PER_REQUEST) as $chunk) {
            $this->addProductsToCollection($collectionId, $chunk);
            $stats['assigned'] += count($chunk);
        }

        return $stats;
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
        $collectionId = $this->lookupCollectionId($categoryId, $handle);

        if ($collectionId !== null) {
            return $collectionId;
        }

        $category = null;

        try {
            $category = Category::query()->find($categoryId);
        } catch (Throwable) {
            // Isolated unit tests may exercise this service before migrations.
        }

        $created = $this->createCollection($category, $handle);
        $collectionId = $created['id'];
        $resolvedHandle = $created['handle'];
        $this->publishCollection($collectionId);

        if ($category) {
            $category->forceFill([
                'shopify_collection_id' => $collectionId,
                'shopify_collection_handle' => $resolvedHandle,
            ])->save();
        }

        return $this->resolvedCollectionIds[$handle] = $collectionId;
    }

    /**
     * Resolve a Shopify collection id from local mapping / Shopify lookup only.
     * Does not create collections and does not persist anything locally.
     */
    private function lookupCollectionId(int $categoryId, string $handle): ?string
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

        if (! is_string($collectionId) || $collectionId === '') {
            return null;
        }

        return $this->resolvedCollectionIds[$handle] = $collectionId;
    }

    private function findCollectionId(int $categoryId, string $handle): ?string
    {
        return $this->lookupCollectionId($categoryId, $handle);
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

    /**
     * @param  array<int, string>  $shopifyProductIds
     */
    private function removeProductsFromCollection(string $collectionId, array $shopifyProductIds): void
    {
        $response = $this->query([
            'query' => self::COLLECTION_REMOVE_PRODUCTS_MUTATION,
            'variables' => [
                'id' => $collectionId,
                'productIds' => array_values($shopifyProductIds),
            ],
        ]);

        $result = $response->data['collectionRemoveProducts'] ?? [];

        $this->assertNoUserErrors($this->withoutAlreadyRemovedErrors($result['userErrors'] ?? []));
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

    /**
     * @param  array<int, mixed>  $errors
     * @return array<int, mixed>
     */
    private function withoutAlreadyRemovedErrors(array $errors): array
    {
        return array_values(array_filter($errors, function ($error) {
            $message = is_array($error) ? strtolower((string) ($error['message'] ?? '')) : '';

            return ! str_contains($message, 'already')
                && ! str_contains($message, 'not found')
                && ! str_contains($message, 'does not exist');
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
