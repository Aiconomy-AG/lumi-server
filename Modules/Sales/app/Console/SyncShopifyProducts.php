<?php

namespace Modules\Sales\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Sales\Exceptions\Shopify\ShopifyException;
use Modules\Sales\Integrations\Shopify\ProductSyncService;
use Modules\Sales\Models\Product;

#[Signature('sales:sync-shopify-products
    {--sync : Push pending products synchronously instead of queueing jobs}
    {--all : Re-sync every product, not only pending ones}
    {--product= : Sync a single local product id synchronously}
    {--sku= : Sync a single product by parent or variant sku synchronously}')]
#[Description('Push local products to Shopify (create/update + Online Store publish)')]
class SyncShopifyProducts extends Command
{
    public function handle(ProductSyncService $shopify): int
    {
        if ($this->option('sku') !== null) {
            $product = $this->findProductBySku((string) $this->option('sku'));

            if ($product === null) {
                $this->components->error('No product found for sku '.$this->option('sku'));

                return self::FAILURE;
            }

            return $this->syncSingle($shopify, (int) $product->id, (string) $product->name);
        }

        if ($this->option('product') !== null) {
            return $this->syncSingle($shopify, (int) $this->option('product'));
        }

        if ($this->option('sync')) {
            $this->components->info('Syncing pending products synchronously...');
            $shopify->seed();
            $this->components->info('Done. Products with errors keep shopify_sync_status=error; re-run to retry.');

            return self::SUCCESS;
        }

        $queued = $this->option('all') ? $shopify->queueAll() : $shopify->queueSeed();

        $this->components->info(sprintf(
            'Queued %d product sync job(s). Run a worker on shopify-sync: php artisan queue:work redis --queue=shopify-sync',
            $queued,
        ));

        return self::SUCCESS;
    }

    private function syncSingle(ProductSyncService $shopify, int $productId, ?string $productName = null): int
    {
        $product = Product::query()
            ->with(['variants', 'category', 'ingredients' => fn ($query) => $query->orderBy('product_ingredients.id')])
            ->find($productId);

        if ($product === null) {
            $this->components->error("Product {$productId} was not found.");

            return self::FAILURE;
        }

        try {
            $shopify->sync($product);
        } catch (ShopifyException $exception) {
            $this->components->error("Product {$productId} failed to sync: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $label = $productName ?? $product->name;

        $this->components->info(sprintf(
            'Product %d (%s) synced to Shopify as %s with %d variant(s).',
            $productId,
            $label,
            (string) $product->shopify_product_id,
            $product->variants->count(),
        ));

        return self::SUCCESS;
    }

    private function findProductBySku(string $sku): ?Product
    {
        $sku = trim($sku);

        if ($sku === '') {
            return null;
        }

        $product = Product::query()->where('sku', $sku)->first();

        if ($product !== null) {
            return $product;
        }

        return Product::query()
            ->whereHas('variants', fn ($query) => $query->where('sku', $sku))
            ->first();
    }
}
