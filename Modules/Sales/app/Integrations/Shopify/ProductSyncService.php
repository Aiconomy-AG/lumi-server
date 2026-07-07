<?php

namespace Modules\Sales\Integrations\Shopify;

use Illuminate\Support\Facades\Log;
use Modules\Sales\Enums\ShopifySyncStatus;
use Modules\Sales\Exceptions\Shopify\ShopifyException;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;

class ProductSyncService
{
    private const PRODUCT_SET_MUTATION = <<<'GRAPHQL'
    mutation ProductSet($input: ProductSetInput!) {
        productSet(synchronous: true, input: $input) {
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
    query Products($cursor: String) {
        products(first: 100, after: $cursor) {
            edges { node { id } }
            pageInfo { hasNextPage endCursor }
        }
    }
    GRAPHQL;

    public function __construct(
        private readonly ShopifyConnector $connector,
    ) {}

    public function sync(Product $product): void
    {
        $product->forceFill(['shopify_sync_status' => ShopifySyncStatus::Syncing])->save();

        try {
            $shopifyId = $this->push($product);

            $product->forceFill([
                'shopify_product_id' => $shopifyId,
                'shopify_sync_status' => ShopifySyncStatus::Synced,
            ])->save();
        } catch (ShopifyException $exception) {
            $product->forceFill(['shopify_sync_status' => ShopifySyncStatus::Error])->save();

            Log::warning('Shopify product sync failed', [
                'product_id' => $product->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function delete(Product $product): void
    {
        if (empty($product->shopify_product_id)) {
            return;
        }

        $response = $this->connector->query([
            'query' => self::PRODUCT_DELETE_MUTATION,
            'variables' => ['input' => ['id' => $product->shopify_product_id]],
        ]);

        $this->assertNoUserErrors($response->data['productDelete']['userErrors'] ?? []);

        $product->forceFill([
            'shopify_product_id' => null,
            'shopify_sync_status' => ShopifySyncStatus::Unsynced,
        ])->save();
    }

    /**
     * Delete every product currently in the Shopify store (paginating through
     * the whole catalog) and reset local sync state so they can be re-created.
     *
     * @return int Number of products deleted from Shopify.
     */
    public function deleteAll(): int
    {
        $deleted = 0;
        $cursor = null;

        do {
            $response = $this->connector->query([
                'query' => self::PRODUCTS_QUERY,
                'variables' => ['cursor' => $cursor],
            ]);

            $products = $response->data['products'] ?? [];

            foreach ($products['edges'] ?? [] as $edge) {
                $id = $edge['node']['id'] ?? null;

                if (is_string($id) && $id !== '') {
                    $this->deleteById($id);
                    $deleted++;
                }
            }

            $hasNextPage = (bool) ($products['pageInfo']['hasNextPage'] ?? false);
            $cursor = $products['pageInfo']['endCursor'] ?? null;
        } while ($hasNextPage && $cursor !== null);

        Product::query()->update([
            'shopify_product_id' => null,
            'shopify_sync_status' => ShopifySyncStatus::Unsynced->value,
        ]);

        return $deleted;
    }

    private function deleteById(string $shopifyProductId): void
    {
        $response = $this->connector->query([
            'query' => self::PRODUCT_DELETE_MUTATION,
            'variables' => ['input' => ['id' => $shopifyProductId]],
        ]);

        $this->assertNoUserErrors($response->data['productDelete']['userErrors'] ?? []);
    }

    public function seed(): void
    {
        Product::query()
            ->with('variants')
            ->where(function ($query) {
                $query->whereNull('shopify_product_id')
                    ->orWhereIn('shopify_sync_status', [
                        ShopifySyncStatus::Unsynced->value,
                        ShopifySyncStatus::Error->value,
                    ]);
            })
            ->chunkById(100, function ($products) {
                foreach ($products as $product) {
                    $this->sync($product);
                }
            });
    }

    private function push(Product $product): string
    {
        $response = $this->connector->query([
            'query' => self::PRODUCT_SET_MUTATION,
            'variables' => ['input' => $this->buildInput($product)],
        ]);

        $result = $response->data['productSet'] ?? [];

        $this->assertNoUserErrors($result['userErrors'] ?? []);

        $shopifyId = $result['product']['id'] ?? null;

        if (! is_string($shopifyId) || $shopifyId === '') {
            throw new ShopifyException('Shopify did not return a product id.');
        }

        return $shopifyId;
    }

    private function buildInput(Product $product): array
    {
        [$productOptions, $variants] = $this->buildVariants($product);

        $input = [
            'title' => $product->name,
            'descriptionHtml' => (string) $product->description,
            'status' => 'ACTIVE',
            'productOptions' => $productOptions,
            'variants' => $variants,
        ];

        if (! empty($product->shopify_product_id)) {
            $input['id'] = $product->shopify_product_id;
        } elseif (! empty($product->image_url)) {
            $input['files'] = [
                ['originalSource' => $product->image_url, 'contentType' => 'IMAGE'],
            ];
        }

        return $input;
    }

    /**
     * Build the Shopify product option definition together with one Shopify
     * variant per stored variant. A product is classified either by colour or
     * by size/weight, so it is pushed under a single "Color" or "Size" option
     * (each variant becoming its own Shopify variant). Products without a
     * meaningful classification fall back to the default "Title" option.
     *
     * @return array{0: array<int, array>, 1: array<int, array>}
     */
    private function buildVariants(Product $product): array
    {
        $variants = $product->variants;

        if ($this->hasDistinctLabels($variants, fn (ProductVariant $variant) => $this->colourLabel($variant))) {
            return $this->buildOptionVariants($product, 'Color', fn (ProductVariant $variant) => $this->colourLabel($variant));
        }

        if ($variants->count() > 1
            && $this->hasDistinctLabels($variants, fn (ProductVariant $variant) => $this->sizeLabel($variant))) {
            return $this->buildOptionVariants($product, 'Size', fn (ProductVariant $variant) => $this->sizeLabel($variant));
        }

        return [
            [['name' => 'Title', 'values' => [['name' => 'Default Title']]]],
            [$this->buildVariant($variants->first(), $product, 'Title', 'Default Title')],
        ];
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
                $optionName,
                $label($variant),
            ))
            ->values()
            ->all();

        return [$productOptions, $variantInputs];
    }

    private function buildVariant(?ProductVariant $variant, Product $product, string $optionName, string $optionValue): array
    {
        $data = [
            'price' => $this->money($variant?->price ?? $product->price),
            'optionValues' => [
                ['optionName' => $optionName, 'name' => $optionValue],
            ],
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
     * Human-readable size label built from the variant weight + unit, e.g.
     * 30 + "ml" => "30ml". Returns null when there is no meaningful size.
     */
    private function sizeLabel(ProductVariant $variant): ?string
    {
        if ($variant->weight === null || (float) $variant->weight <= 0.0) {
            return null;
        }

        $value = rtrim(rtrim(number_format((float) $variant->weight, 2, '.', ''), '0'), '.');
        $unit = $variant->weight_unit !== null ? trim($variant->weight_unit) : '';

        return $unit !== '' ? $value.$unit : $value;
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
