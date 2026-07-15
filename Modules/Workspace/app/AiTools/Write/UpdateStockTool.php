<?php

namespace Modules\Workspace\AiTools\Write;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Modules\Sales\Services\InventoryService;
use Modules\Workspace\AiTools\AbstractAiTool;

class UpdateStockTool extends AbstractAiTool
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function name(): string
    {
        return 'update_stock';
    }

    public function description(): string
    {
        return 'Update stock quantity for a single product variant by SKU. Admin only.';
    }

    public function isWrite(): bool
    {
        return true;
    }

    protected function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sku' => ['type' => 'string', 'description' => 'Unique product variant SKU'],
                'stock_quantity' => ['type' => 'integer', 'description' => 'New stock quantity (>= 0)'],
            ],
            'required' => ['sku', 'stock_quantity'],
        ];
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:255', 'exists:product_variants,sku'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
        ];
    }

    public function authorize(User $user, array $arguments): bool
    {
        $variant = ProductVariant::query()->where('sku', $arguments['sku'] ?? '')->first();
        if (! $variant) {
            return false;
        }

        $product = Product::query()->find($variant->product_id);

        return $product && Gate::forUser($user)->allows('updateStock', $product);
    }

    public function execute(User $user, array $arguments): array
    {
        $validated = $this->validate($arguments);

        $variant = ProductVariant::query()->where('sku', $validated['sku'])->firstOrFail();
        $oldStock = $variant->stock_quantity;

        $variant = $this->inventory->updateStockBySku(
            $validated['sku'],
            $validated['stock_quantity'],
            $user,
            'AI-proposed stock adjustment.',
        );

        $variant->load('product:id,name');

        return [
            'sku' => $variant->sku,
            'product_name' => $variant->product?->name,
            'old_stock_quantity' => $oldStock,
            'new_stock_quantity' => $variant->stock_quantity,
        ];
    }

    public function summarize(array $arguments): string
    {
        $sku = $arguments['sku'] ?? '?';
        $qty = $arguments['stock_quantity'] ?? '?';

        return "Set stock of SKU {$sku} to {$qty}";
    }
}
