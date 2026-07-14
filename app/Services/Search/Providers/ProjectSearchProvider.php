<?php

namespace App\Services\Search\Providers;

use App\Services\Search\AbstractSearchProvider;
use Illuminate\Database\Eloquent\Model;
use Meilisearch\Contracts\SearchQuery;
use Modules\Workspace\Models\Project;

class ProjectSearchProvider extends AbstractSearchProvider
{
    public function type(): string
    {
        return 'project';
    }

    protected function model(): Model
    {
        return new Project;
    }

    public function buildSearchQuery(string $query, int $limit, bool $includeCompleted): SearchQuery
    {
        $searchQuery = $this->baseQuery($query, $limit);

        if ($filter = $this->completedStatusFilter($includeCompleted)) {
            $searchQuery->setFilter([$filter]);
        }

        return $searchQuery;
    }

    protected function modelToHit(Model $model): array
    {
        /** @var Project $model */
        return [
            'id' => (int) $model->id,
            'name' => $model->name,
            'status' => $model->status,
        ];
    }

    public function mapHit(array $hit): array
    {
        return [
            'type' => 'project',
            'module' => 'workspace',
            'id' => (int) $hit['id'],
            'title' => (string) ($hit['name'] ?? ''),
            'subtitle' => $hit['status'] ?? null,
            'url' => '/projects/'.$hit['id'],
            'meta' => [
                'status' => $hit['status'] ?? null,
            ],
        ];
    }
}
