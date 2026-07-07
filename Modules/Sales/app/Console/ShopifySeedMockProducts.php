<?php

namespace Modules\Sales\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Sales\Integrations\Shopify\ProductSyncService;
use Modules\Sales\Models\Product;

#[Signature('shopify:seed-mock-products {count=3 : How many mock products to create}')]
#[Description('Create mock products and push them to Shopify')]
class ShopifySeedMockProducts extends Command
{
    public const MOCK_PREFIX = '[MOCK] ';

    public function handle(ProductSyncService $sync): int
    {
        $count = max(1, (int) $this->argument('count'));

        $products = Product::factory()
            ->count($count)
            ->create([
                'image_url' => null,
            ]);

        foreach ($products as $product) {
            $product->forceFill([
                'name' => self::MOCK_PREFIX.$product->name,
            ])->save();
        }

        $this->components->info("Created {$count} mock products. Pushing to Shopify...");

        $sync->seed();

        $this->table(
            ['ID', 'Name', 'Shopify ID', 'Status'],
            Product::query()
                ->whereIn('id', $products->pluck('id'))
                ->get()
                ->map(fn (Product $product) => [
                    $product->id,
                    $product->name,
                    $product->shopify_product_id ?? '-',
                    $product->shopify_sync_status->value,
                ]),
        );

        return self::SUCCESS;
    }
}
