<?php

namespace Modules\Sales\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Integrations\Shopify\ProductSyncService;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;

class InventoryService
{
    public function __construct(
        private readonly ProductSyncService $shopify,
    ) {}

    public function updateStockBySku(
        string $sku,
        int $stockQuantity,
        ?User $actor = null,
        ?string $reason = null,
    ): ProductVariant {
        $variant = ProductVariant::query()->where('sku', $sku)->firstOrFail();
        $product = Product::query()->findOrFail($variant->product_id);

        $oldStock = $variant->stock_quantity;
        $newStock = $stockQuantity;

        DB::transaction(function () use ($variant, $newStock, $oldStock, $product, $reason, $actor): void {
            $variant->update(['stock_quantity' => $newStock]);

            if ($newStock !== $oldStock) {
                AuditLog::record(
                    module: 'sales',
                    action: 'stock_update',
                    entity: $variant,
                    label: $product->name.' ('.$variant->sku.')',
                    changes: [
                        'old' => ['stock_quantity' => $oldStock],
                        'new' => ['stock_quantity' => $newStock],
                    ],
                    description: $reason ?? 'Manual inventory stock adjustment.',
                    actor: $actor,
                );
            }
        });

        $product->load(['variants', 'category']);
        $this->shopify->updateVariant($product);

        return $variant->refresh();
    }

    public function updateStock(
        Product $product,
        ProductVariant $variant,
        int $stockQuantity,
        ?User $actor = null,
        ?string $reason = null,
    ): ProductVariant {
        return $this->updateStockBySku(
            $variant->sku,
            $stockQuantity,
            $actor,
            $reason,
        );
    }
}
