<?php

namespace App\Services\Search\Providers;

use App\Services\Search\AbstractSearchProvider;
use Illuminate\Database\Eloquent\Model;
use Meilisearch\Contracts\SearchQuery;
use Modules\Workspace\Models\Task;

class TaskSearchProvider extends AbstractSearchProvider
{
    public function type(): string
    {
        return 'task';
    }

    protected function model(): Model
    {
        return new Task;
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
        /** @var Task $model */
        $model->loadMissing('project');

        return [
            'id' => (int) $model->id,
            'title' => $model->title,
            'project_name' => $model->project?->name,
            'status' => $model->status,
        ];
    }

    public function mapHit(array $hit): array
    {
        return [
            'type' => 'task',
            'module' => 'workspace',
            'id' => (int) $hit['id'],
            'title' => (string) ($hit['title'] ?? ''),
            'subtitle' => $hit['project_name'] ?? null,
            'url' => '/tasks/'.$hit['id'],
            'meta' => [
                'status' => $hit['status'] ?? null,
            ],
        ];
    }
}
