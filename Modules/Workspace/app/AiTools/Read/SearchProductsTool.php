<?php

namespace Modules\Workspace\AiTools\Read;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Modules\Workspace\AiTools\AbstractAiTool;

class SearchProductsTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'search_products';
    }

    public function description(): string
    {
        return 'Search product variants by SKU or product name. Returns variants with stock quantities.';
    }

    public function isWrite(): bool
    {
        return false;
    }

    protected function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'SKU or product/variant name to search'],
            ],
            'required' => ['query'],
        ];
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:255'],
        ];
    }

    public function authorize(User $user, array $arguments): bool
    {
        return Gate::forUser($user)->allows('viewAny', Product::class);
    }

    public function execute(User $user, array $arguments): array
    {
        $validated = $this->validate($arguments);
        $query = $validated['query'];

        $variants = ProductVariant::query()
            ->with('product:id,name')
            ->where(function ($q) use ($query) {
                $q->where('sku', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$query}%"));
            })
            ->limit(30)
            ->get();

        return [
            'variants' => $variants->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'name' => $v->name,
                'product_id' => $v->product_id,
                'product_name' => $v->product?->name,
                'stock_quantity' => $v->stock_quantity,
            ])->all(),
        ];
    }

    public function summarize(array $arguments): string
    {
        return 'Search products: '.($arguments['query'] ?? '');
    }
}
