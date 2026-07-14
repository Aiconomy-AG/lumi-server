<?php

namespace App\Services\Search\Contracts;

use App\Models\User;
use Meilisearch\Contracts\SearchQuery;

interface SearchProvider
{
    public function type(): string;

    public function index(): string;

    public function isAvailableFor(User $user): bool;

    public function buildSearchQuery(string $query, int $limit, bool $includeCompleted): SearchQuery;

    /**
     * @param  array<string, mixed>  $hit
     * @return array<string, mixed>
     */
    public function mapHit(array $hit): array;
}
