<?php

namespace App\Services\Search\Providers;

use App\Models\User;
use App\Services\Search\AbstractSearchProvider;
use Illuminate\Database\Eloquent\Model;
use Meilisearch\Contracts\SearchQuery;

class UserSearchProvider extends AbstractSearchProvider
{
    public function type(): string
    {
        return 'user';
    }

    protected function model(): Model
    {
        return new User;
    }

    public function buildSearchQuery(string $query, int $limit, bool $includeCompleted): SearchQuery
    {
        return $this->baseQuery($query, $limit);
    }

    protected function modelToHit(Model $model): array
    {
        /** @var User $model */
        return [
            'id' => (int) $model->id,
            'name' => $model->name,
            'email' => $model->email,
            'role' => $model->role?->value,
        ];
    }

    public function mapHit(array $hit): array
    {
        return [
            'type' => 'user',
            'module' => 'core',
            'id' => (int) $hit['id'],
            'title' => (string) ($hit['name'] ?? ''),
            'subtitle' => $hit['email'] ?? null,
            'url' => '/chat?user='.$hit['id'],
            'meta' => [
                'role' => $hit['role'] ?? null,
            ],
        ];
    }
}
