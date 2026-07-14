<?php

namespace App\Services\Search;

use App\Models\User;
use App\Services\Search\Contracts\SearchProvider;
use Illuminate\Database\Eloquent\Model;
use Meilisearch\Contracts\SearchQuery;

abstract class AbstractSearchProvider implements SearchProvider
{
    abstract protected function model(): Model;

    public function isAvailableFor(User $user): bool
    {
        return true;
    }

    public function index(): string
    {
        return $this->model()->searchableAs();
    }

    protected function baseQuery(string $query, int $limit): SearchQuery
    {
        return (new SearchQuery)
            ->setIndexUid($this->index())
            ->setQuery($query)
            ->setHitsPerPage($limit)
            ->setSort(['updated_at:desc']);
    }

    protected function completedStatusFilter(bool $includeCompleted): ?string
    {
        return $includeCompleted ? null : 'status != "complete"';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchFallback(string $query, int $limit, bool $includeCompleted): array
    {
        $modelClass = $this->model()::class;
        $builder = $modelClass::search($query)->take($limit);

        if ($filter = $this->completedStatusFilter($includeCompleted)) {
            $builder->where('status', '!=', 'complete');
        }

        return $builder->get()
            ->map(fn (Model $model) => $this->mapHit($this->modelToHit($model)))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function modelToHit(Model $model): array;

    /**
     * @param  array<string, mixed>  $hit
     * @return array<string, mixed>
     */
    abstract public function mapHit(array $hit): array;
}
