<?php

namespace Modules\Sales\Integrations\Shopify;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Sales\Enums\ShopifySyncStatus;
use Modules\Sales\Exceptions\Shopify\ShopifyException;
use Modules\Sales\Exceptions\Shopify\ShopifyThrottledException;
use Modules\Sales\Jobs\DeleteShopifyProductJob;
use Modules\Sales\Jobs\SyncShopifyProductJob;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Modules\Sales\Services\IngredientShopifyFormatter;

class ProductSyncService
{
    /**
     * How many times a single request will wait out a Shopify rate limit
     * before giving up.
     */
    private const MAX_THROTTLE_RETRIES = 10;

    private const PRODUCT_SET_MUTATION = <<<'GRAPHQL'
    mutation ProductSet($identifier: ProductSetIdentifiers!, $input: ProductSetInput!) {
        productSet(synchronous: true, identifier: $identifier, input: $input) {
            product { id }
            userErrors { field message }
        }
    }
    GRAPHQL;

    private const PRODUCT_DELETE_MUTATION = <<<'GRAPHQL'
    mutation ProductDelete($input: ProductDeleteInput!) {
        productDelete(input: $input) {
            deletedProductId
            userErrors { field message }
        }
    }
    GRAPHQL;

    private const PRODUCTS_QUERY = <<<'GRAPHQL'
    query Products {
        products(first: 100) {
            edges { node { id } }
        }
    }
    GRAPHQL;

    private const METAFIELDS_SET_MUTATION = <<<'GRAPHQL'
    mutation MetafieldsSet($metafields: [MetafieldsSetInput!]!) {
        metafieldsSet(metafields: $metafields) {
            metafields { id namespace key }
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

    private const PUBLISHABLE_PUBLISH_MUTATION = <<<'GRAPHQL'
    mutation PublishablePublish($id: ID!, $input: [PublicationInput!]!) {
        publishablePublish(id: $id, input: $input) {
            publishable { __typename }
            userErrors { field message }
        }
    }
    GRAPHQL;

    private ?string $onlineStorePublicationId = null;

    public function __construct(
        private readonly ShopifyConnector $connector,
        private readonly CollectionAssignService $collectionAssignService,
    ) {}

    /**
     * Queue a newly created local product for its first Shopify push.
     */
    public function create(Product $product): void
    {
        $this->queueSync($product);
    }

    /**
     * Queue product field changes for re-sync to Shopify.
     */
    public function update(Product $product): void
    {
        $this->queueSync($product);
    }

    /**
     * Queue a parent product re-sync after a variant is created locally.
     */
    public function createVariant(Product $product): void
    {
        $this->queueSync($product);
    }

    /**
     * Queue a parent product re-sync after a variant is updated locally.
     */
    public function updateVariant(Product $product): void
    {
        $this->queueSync($product);
    }

    /**
     * Queue a parent product re-sync after a variant is deleted locally.
     */
    public function deleteVariant(Product $product): void
    {
        $this->queueSync($product);
    }

    /**
     * Queue removal of a product from Shopify when the local record is deleted.
     */
    public function queueDelete(Product $product): void
    {
        $shopifyProductId = $product->shopify_product_id;

        if (! is_string($shopifyProductId) || $shopifyProductId === '') {
            return;
        }

        DeleteShopifyProductJob::dispatch($shopifyProductId);
    }

    /**
     * Delete a product from Shopify by its remote id. Idempotent when the
     * product has already been removed.
     */
    public function deleteRemote(string $shopifyProductId): void
    {
        $this->deleteById($shopifyProductId);
    }

    public function sync(Product $product): void
    {
        $this->loadSyncRelations($product);

        $product->forceFill(['shopify_sync_status' => ShopifySyncStatus::Syncing])->save();

        try {
            $shopifyId = $this->push($product);
            $this->pushIngredientsMetafield($shopifyId, $product);
            $this->publishToOnlineStore($shopifyId);

            $product->forceFill([
                'shopify_product_id' => $shopifyId,
                'shopify_sync_status' => ShopifySyncStatus::Synced,
            ])->save();

            $this->assignProductCollection($product, $shopifyId);
        } catch (ShopifyException $exception) {
            $product->forceFill(['shopify_sync_status' => ShopifySyncStatus::Error])->save();

            Log::warning('Shopify product sync failed', [
                'product_id' => $product->getKey(),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function delete(Product $product): void
    {
        if (empty($product->shopify_product_id)) {
            return;
        }

        $this->deleteById($product->shopify_product_id);

        $product->forceFill([
            'shopify_product_id' => null,
            'shopify_sync_status' => ShopifySyncStatus::Unsynced,
        ])->save();
    }

    /**
     * Delete every product currently in the Shopify store and reset local sync
     * state so they can be re-created.
     *
     * The store is drained by repeatedly fetching the first page: because each
     * pass deletes the products it sees, the catalog shrinks until it is empty.
     * This avoids cursor pagination shifting under concurrent deletes (which
     * would revisit already-deleted ids).
     *
     * @return int Number of products deleted from Shopify.
     */
    public function deleteAll(): int
    {
        $deleted = 0;

        while (true) {
            $response = $this->query(['query' => self::PRODUCTS_QUERY]);

            $edges = $response->data['products']['edges'] ?? [];

            if ($edges === []) {
                break;
            }

            $progressed = false;

            foreach ($edges as $edge) {
                $id = $edge['node']['id'] ?? null;

                if (! is_string($id) || $id === '') {
                    continue;
                }

                if ($this->deleteById($id)) {
                    $deleted++;
                    $progressed = true;
                }
            }

            // Nothing on this page could actually be deleted; stop rather than
            // loop forever on ids Shopify keeps returning.
            if (! $progressed) {
                break;
            }
        }

        Product::query()->update([
            'shopify_product_id' => null,
            'shopify_sync_status' => ShopifySyncStatus::Unsynced->value,
        ]);

        return $deleted;
    }

    /**
     * Delete a single Shopify product. Idempotent: a product that no longer
     * exists is treated as already deleted rather than an error.
     *
     * @return bool Whether Shopify actually deleted a product.
     */
    private function deleteById(string $shopifyProductId): bool
    {
        $response = $this->query([
            'query' => self::PRODUCT_DELETE_MUTATION,
            'variables' => ['input' => ['id' => $shopifyProductId]],
        ]);

        $result = $response->data['productDelete'] ?? [];
        $errors = $this->withoutMissingProductErrors($result['userErrors'] ?? []);

        $this->assertNoUserErrors($errors);

        return ($result['deletedProductId'] ?? null) !== null;
    }

    /**
     * Drop "product does not exist" user errors so deleting an already-removed
     * product is a no-op instead of a failure.
     *
     * @param  array<int, mixed>  $errors
     * @return array<int, mixed>
     */
    private function withoutMissingProductErrors(array $errors): array
    {
        return array_values(array_filter($errors, function ($error) {
            $message = is_array($error) ? (string) ($error['message'] ?? '') : '';

            return stripos($message, 'does not exist') === false;
        }));
    }

    /**
     * Synchronously push every product that still needs syncing. Simple but
     * slow for large catalogs; prefer queueSeed() for those.
     */
    public function seed(): void
    {
        $this->pendingProducts()
            ->with('variants')
            ->chunkById(100, function ($products) {
                foreach ($products as $product) {
                    $this->sync($product);
                }
            });
    }

    /**
     * Dispatch one background job per product that still needs syncing, so a
     * large catalog is pushed to Shopify by queue workers instead of blocking
     * the caller. Requires a worker on the "shopify-sync" queue.
     *
     * @return int Number of products queued.
     */
    public function queueSeed(): int
    {
        $queued = 0;

        $this->pendingProducts()
            ->select('id')
            ->chunkById(500, function ($products) use (&$queued) {
                foreach ($products as $product) {
                    SyncShopifyProductJob::dispatch($product->id);
                    $queued++;
                }
            });

        return $queued;
    }

    /**
     * Mark every product as needing a Shopify sync and queue jobs for all of them.
     *
     * @return int Number of products queued.
     */
    public function queueAll(): int
    {
        Product::query()->update([
            'shopify_sync_status' => ShopifySyncStatus::Unsynced->value,
        ]);

        $queued = 0;

        Product::query()
            ->select('id')
            ->chunkById(500, function ($products) use (&$queued) {
                foreach ($products as $product) {
                    SyncShopifyProductJob::dispatch($product->id);
                    $queued++;
                }
            });

        return $queued;
    }

    private function queueSync(Product $product): void
    {
        $product->forceFill(['shopify_sync_status' => ShopifySyncStatus::Unsynced])->save();

        SyncShopifyProductJob::dispatch($product->id);
    }

    /**
     * Products that are not yet synced (never pushed, or last attempt failed).
     */
    private function pendingProducts(): \Illuminate\Database\Eloquent\Builder
    {
        return Product::query()->where(function ($query) {
            $query->whereNull('shopify_product_id')
                ->orWhereIn('shopify_sync_status', [
                    ShopifySyncStatus::Unsynced->value,
                    ShopifySyncStatus::Error->value,
                ]);
        });
    }

    /**
     * Run a Shopify GraphQL request, transparently waiting out rate limits
     * (throttling) instead of letting them abort a long-running sync/delete.
     */
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

    private function push(Product $product): string
    {
        $response = $this->query([
            'query' => self::PRODUCT_SET_MUTATION,
            'variables' => [
                'identifier' => ['handle' => $this->productHandle($product)],
                'input' => $this->buildInput($product),
            ],
        ]);

        $result = $response->data['productSet'] ?? [];

        $this->assertNoUserErrors($result['userErrors'] ?? []);

        $shopifyId = $result['product']['id'] ?? null;

        if (! is_string($shopifyId) || $shopifyId === '') {
            throw new ShopifyException('Shopify did not return a product id.');
        }

        return $shopifyId;
    }

    private function loadSyncRelations(Product $product): void
    {
        $product->loadMissing([
            'category',
            'variants',
            'ingredients' => fn ($query) => $query->orderBy('product_ingredients.id'),
        ]);
    }

    private function pushIngredientsMetafield(string $shopifyProductId, Product $product): void
    {
        $config = config('sales.shopify.ingredients_metafield', []);
        $namespace = (string) ($config['namespace'] ?? 'custom');
        $key = (string) ($config['key'] ?? 'ingredients');
        $type = (string) ($config['type'] ?? 'rich_text_field');

        $value = IngredientShopifyFormatter::toMetafieldValue($product->ingredients, $type);

        if ($value === null) {
            return;
        }

        $response = $this->query([
            'query' => self::METAFIELDS_SET_MUTATION,
            'variables' => [
                'metafields' => [[
                    'ownerId' => $shopifyProductId,
                    'namespace' => $namespace,
                    'key' => $key,
                    'type' => $type,
                    'value' => $value,
                ]],
            ],
        ]);

        $result = $response->data['metafieldsSet'] ?? [];

        $this->assertNoUserErrors($result['userErrors'] ?? []);
    }

    private function buildInput(Product $product): array
    {
        [$productOptions, $variants] = $this->buildVariants($product);

        $input = [
            'handle' => $this->productHandle($product),
            'title' => $product->name,
            'descriptionHtml' => (string) $product->description,
            'status' => 'ACTIVE',
            'productOptions' => $productOptions,
            'variants' => $variants,
        ];

        if ($product->category) {
            $input['productType'] = $product->category->name;
            $input['tags'] = [
                $product->category->name,
                'category:'.$product->category->name,
                'category-handle:'.($product->category->shopify_collection_handle ?: str($product->category->name)->slug()),
            ];
        }

        if (! empty($product->image_url)) {
            $input['files'] = [
                [
                    'originalSource' => $product->image_url,
                    'contentType' => 'IMAGE',
                ],
            ];
        }

        return $input;
    }

    private function publishToOnlineStore(string $shopifyProductId): void
    {
        if (! (bool) config('sales.shopify.publish_products', true)) {
            return;
        }

        $publicationId = $this->onlineStorePublicationId();

        if ($publicationId === null) {
            throw new ShopifyException('Online Store publication was not found.');
        }

        $response = $this->query([
            'query' => self::PUBLISHABLE_PUBLISH_MUTATION,
            'variables' => [
                'id' => $shopifyProductId,
                'input' => [['publicationId' => $publicationId]],
            ],
        ]);

        $result = $response->data['publishablePublish'] ?? [];
        $this->assertNoUserErrors($this->withoutAlreadyPublishedErrors($result['userErrors'] ?? []));
    }

    private function onlineStorePublicationId(): ?string
    {
        if ($this->onlineStorePublicationId !== null) {
            return $this->onlineStorePublicationId;
        }

        $configured = config('sales.shopify.online_store_publication_id');

        if (is_string($configured) && $configured !== '') {
            return $this->onlineStorePublicationId = $configured;
        }

        $response = $this->query(['query' => self::PUBLICATIONS_QUERY]);
        $edges = $response->data['publications']['edges'] ?? [];

        foreach ($edges as $edge) {
            $node = is_array($edge) ? ($edge['node'] ?? null) : null;

            if (! is_array($node)) {
                continue;
            }

            if (strtolower((string) ($node['name'] ?? '')) === 'online store') {
                return $this->onlineStorePublicationId = (string) $node['id'];
            }
        }

        return null;
    }

    private function assignProductCollection(Product $product, string $shopifyProductId): void
    {
        if ($product->category_id === null) {
            return;
        }

        try {
            $this->collectionAssignService->assignProducts((int) $product->category_id, [$shopifyProductId]);
        } catch (ShopifyException $exception) {
            Log::warning('Shopify collection assignment after product sync failed', [
                'product_id' => $product->getKey(),
                'category_id' => $product->category_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $errors
     * @return array<int, mixed>
     */
    private function withoutAlreadyPublishedErrors(array $errors): array
    {
        return array_values(array_filter($errors, function ($error) {
            $message = is_array($error) ? strtolower((string) ($error['message'] ?? '')) : '';

            return ! str_contains($message, 'already');
        }));
    }

    /**
     * A stable Shopify handle derived from the product SKU. Using it as the
     * productSet identifier makes the push idempotent: a retried or timed-out
     * job re-matches the same Shopify product and updates it instead of
     * creating a duplicate.
     */
    private function productHandle(Product $product): string
    {
        $handle = Str::slug((string) $product->sku);

        return $handle !== '' ? $handle : 'product-'.$product->getKey();
    }

    /**
     * Build the Shopify product option definition together with one Shopify
     * variant per stored variant. A product is classified by colour, by
     * size/weight, or by both:
     *  - colour + size => two options ("Color" and "Size"), each variant a
     *    unique combination;
     *  - colour only    => single "Color" option;
     *  - size only      => single "Size" option;
     *  - otherwise      => the default "Title" option.
     *
     * @return array{0: array<int, array>, 1: array<int, array>}
     */
    private function buildVariants(Product $product): array
    {
        $combined = $this->buildColourSizeVariants($product);

        if ($combined !== null) {
            return $combined;
        }

        if ($this->hasDistinctLabels($product->variants, fn (ProductVariant $variant) => $this->colourLabel($variant))) {
            return $this->buildOptionVariants($product, 'Color', fn (ProductVariant $variant) => $this->colourLabel($variant));
        }

        if ($product->variants->count() > 1
            && $this->hasDistinctLabels($product->variants, fn (ProductVariant $variant) => $this->sizeLabel($variant))) {
            return $this->buildOptionVariants($product, 'Size', fn (ProductVariant $variant) => $this->sizeLabel($variant));
        }

        return [
            [['name' => 'Title', 'values' => [['name' => 'Default Title']]]],
            [$this->buildVariant($product->variants->first(), $product, [
                ['optionName' => 'Title', 'name' => 'Default Title'],
            ])],
        ];
    }

    /**
     * Build a two-dimensional Color + Size product when every variant carries
     * both a colour and a size and the combinations are unique. Returns null
     * when the product is not a clean colour/size grid.
     *
     * @return array{0: array<int, array>, 1: array<int, array>}|null
     */
    private function buildColourSizeVariants(Product $product): ?array
    {
        $variants = $product->variants;

        if ($variants->count() < 2) {
            return null;
        }

        $colours = [];
        $sizes = [];
        $seen = [];

        foreach ($variants as $variant) {
            $colour = $this->colourLabel($variant);
            $size = $this->sizeLabel($variant);

            if ($colour === null || $size === null) {
                return null;
            }

            $key = $colour.'|'.$size;

            if (isset($seen[$key])) {
                return null;
            }

            $seen[$key] = true;
            $colours[$colour] = true;
            $sizes[$size] = true;
        }

        $productOptions = [
            ['name' => 'Color', 'values' => array_map(fn ($c) => ['name' => $c], array_keys($colours))],
            ['name' => 'Size', 'values' => array_map(fn ($s) => ['name' => $s], array_keys($sizes))],
        ];

        $variantInputs = $variants
            ->map(fn (ProductVariant $variant) => $this->buildVariant($variant, $product, [
                ['optionName' => 'Color', 'name' => $this->colourLabel($variant)],
                ['optionName' => 'Size', 'name' => $this->sizeLabel($variant)],
            ]))
            ->values()
            ->all();

        return [$productOptions, $variantInputs];
    }

    /**
     * Every variant must resolve to a non-empty, unique label for it to work
     * as a Shopify option value.
     */
    private function hasDistinctLabels(mixed $variants, callable $label): bool
    {
        if ($variants->isEmpty()) {
            return false;
        }

        $labels = $variants
            ->map($label)
            ->filter(fn (?string $value) => $value !== null && $value !== '');

        return $labels->count() === $variants->count()
            && $labels->unique()->count() === $variants->count();
    }

    /**
     * @return array{0: array<int, array>, 1: array<int, array>}
     */
    private function buildOptionVariants(Product $product, string $optionName, callable $label): array
    {
        $variants = $product->variants;

        $productOptions = [[
            'name' => $optionName,
            'values' => $variants
                ->map(fn (ProductVariant $variant) => ['name' => $label($variant)])
                ->values()
                ->all(),
        ]];

        $variantInputs = $variants
            ->map(fn (ProductVariant $variant) => $this->buildVariant(
                $variant,
                $product,
                [['optionName' => $optionName, 'name' => $label($variant)]],
            ))
            ->values()
            ->all();

        return [$productOptions, $variantInputs];
    }

    /**
     * @param  array<int, array{optionName: string, name: string}>  $optionValues
     */
    private function buildVariant(?ProductVariant $variant, Product $product, array $optionValues): array
    {
        $data = [
            'price' => $this->money($variant?->price ?? $product->price),
            'optionValues' => $optionValues,
        ];

        if ($variant instanceof ProductVariant) {
            if (! empty($variant->sku)) {
                $data['sku'] = $variant->sku;
            }

            $weightUnit = $this->weightUnit($variant->weight_unit);

            if ($weightUnit !== null && $variant->weight !== null) {
                $data['inventoryItem'] = [
                    'measurement' => [
                        'weight' => [
                            'value' => (float) $variant->weight,
                            'unit' => $weightUnit,
                        ],
                    ],
                ];
            }
        }

        return $data;
    }

    /**
     * The colour code stored on the variant, used as the "Color" option value.
     */
    private function colourLabel(ProductVariant $variant): ?string
    {
        $colour = $variant->colour !== null ? trim($variant->colour) : '';

        return $colour !== '' ? $colour : null;
    }

    /**
     * Human-readable size label built from the variant weight + unit:
     * 30 + "ml" => "30ml", null + "m" => "m", 3 + "xl" => "3xl". Returns null
     * when the variant carries neither a weight nor a unit.
     */
    private function sizeLabel(ProductVariant $variant): ?string
    {
        $weight = $variant->weight !== null ? (float) $variant->weight : null;
        $unit = $variant->weight_unit !== null ? trim($variant->weight_unit) : '';

        $value = ($weight !== null && $weight > 0.0)
            ? rtrim(rtrim(number_format($weight, 2, '.', ''), '0'), '.')
            : '';

        $label = $value.$unit;

        return $label !== '' ? $label : null;
    }

    private function money(int|float|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function weightUnit(?string $unit): ?string
    {
        return match (strtolower((string) $unit)) {
            'g', 'gram', 'grams' => 'GRAMS',
            'kg', 'kilogram', 'kilograms' => 'KILOGRAMS',
            'oz', 'ounce', 'ounces' => 'OUNCES',
            'lb', 'lbs', 'pound', 'pounds' => 'POUNDS',
            default => null,
        };
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

        throw new ShopifyException('Shopify rejected the product: '.implode('; ', $messages));
    }
}
