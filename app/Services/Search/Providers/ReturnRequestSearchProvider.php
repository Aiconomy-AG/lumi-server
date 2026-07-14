<?php

namespace App\Services\Search\Providers;

use App\Services\Search\AbstractSearchProvider;
use Illuminate\Database\Eloquent\Model;
use Meilisearch\Contracts\SearchQuery;
use Modules\Sales\Models\ReturnRequest;

class ReturnRequestSearchProvider extends AbstractSearchProvider
{
    public function type(): string
    {
        return 'return';
    }

    protected function model(): Model
    {
        return new ReturnRequest;
    }

    public function buildSearchQuery(string $query, int $limit, bool $includeCompleted): SearchQuery
    {
        return $this->baseQuery($query, $limit);
    }

    protected function modelToHit(Model $model): array
    {
        /** @var ReturnRequest $model */
        return [
            'id' => (int) $model->id,
            'email' => $model->email,
            'shopify_order_name' => $model->shopify_order_name,
            'status' => $model->status,
        ];
    }

    public function mapHit(array $hit): array
    {
        $subtitle = $hit['shopify_order_name'] ?? $hit['email'] ?? null;

        return [
            'type' => 'return',
            'module' => 'sales',
            'id' => (int) $hit['id'],
            'title' => 'Return #'.$hit['id'],
            'subtitle' => $subtitle,
            'url' => '/returns/'.$hit['id'],
            'meta' => [
                'status' => $hit['status'] ?? null,
            ],
        ];
    }
}
