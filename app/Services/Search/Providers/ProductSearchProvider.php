<?php

namespace App\Services\Search\Providers;

use App\Services\Search\AbstractSearchProvider;
use Illuminate\Database\Eloquent\Model;
use Meilisearch\Contracts\SearchQuery;
use Modules\Sales\Models\Product;

class ProductSearchProvider extends AbstractSearchProvider
{
    public function type(): string
    {
        return 'product';
    }

    protected function model(): Model
    {
        return new Product;
    }

    public function buildSearchQuery(string $query, int $limit, bool $includeCompleted): SearchQuery
    {
        return $this->baseQuery($query, $limit);
    }

    protected function modelToHit(Model $model): array
    {
        /** @var Product $model */
        return [
            'id' => (int) $model->id,
            'name' => $model->name,
            'sku' => $model->sku,
        ];
    }

    public function mapHit(array $hit): array
    {
        return [
            'type' => 'product',
            'module' => 'sales',
            'id' => (int) $hit['id'],
            'title' => (string) ($hit['name'] ?? ''),
            'subtitle' => ! empty($hit['sku']) ? 'SKU: '.$hit['sku'] : null,
            'url' => '/stock?product='.$hit['id'],
            'meta' => [
                'sku' => $hit['sku'] ?? null,
            ],
        ];
    }
}
