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
        $input = [
            'title' => $product->name,
            'descriptionHtml' => (string) $product->description,
            'status' => 'ACTIVE',
            'productOptions' => [
                ['name' => 'Title', 'values' => [['name' => 'Default Title']]],
            ],
            'variants' => [$this->buildVariant($product)],
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

    private function buildVariant(Product $product): array
    {
        $variant = $product->variants->first();

        $data = [
            'price' => $this->money($variant?->price ?? $product->price),
            'optionValues' => [
                ['optionName' => 'Title', 'name' => 'Default Title'],
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
