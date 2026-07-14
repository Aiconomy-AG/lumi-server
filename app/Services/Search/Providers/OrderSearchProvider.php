<?php

namespace App\Services\Search\Providers;

use App\Services\Search\AbstractSearchProvider;
use Illuminate\Database\Eloquent\Model;
use Meilisearch\Contracts\SearchQuery;
use Modules\Sales\Models\Order;

class OrderSearchProvider extends AbstractSearchProvider
{
    public function type(): string
    {
        return 'order';
    }

    protected function model(): Model
    {
        return new Order;
    }

    public function buildSearchQuery(string $query, int $limit, bool $includeCompleted): SearchQuery
    {
        return $this->baseQuery($query, $limit);
    }

    protected function modelToHit(Model $model): array
    {
        /** @var Order $model */
        $model->loadMissing('customer');

        return [
            'id' => (int) $model->id,
            'shopify_order_name' => $model->shopify_order_name,
            'customer_email' => $model->customer?->email,
            'customer_username' => $model->customer?->username,
            'status' => $model->status,
            'payment_status' => $model->payment_status,
        ];
    }

    public function mapHit(array $hit): array
    {
        $subtitle = $hit['shopify_order_name']
            ?? $hit['customer_email']
            ?? $hit['customer_username']
            ?? null;

        return [
            'type' => 'order',
            'module' => 'sales',
            'id' => (int) $hit['id'],
            'title' => 'Order #'.$hit['id'],
            'subtitle' => $subtitle,
            'url' => '/orders/'.$hit['id'],
            'meta' => [
                'status' => $hit['status'] ?? null,
                'payment_status' => $hit['payment_status'] ?? null,
            ],
        ];
    }
}
