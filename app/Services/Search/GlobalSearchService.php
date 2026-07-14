<?php

namespace App\Services\Search;

use App\Models\User;
use App\Services\Search\Contracts\SearchProvider;
use Illuminate\Support\Collection;
use Meilisearch\Client as MeilisearchClient;

class GlobalSearchService
{
    /**
     * @param  iterable<int, SearchProvider>  $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly MeilisearchClient $meilisearch,
    ) {}

    /**
     * @param  list<string>|null  $types
     * @return array{query: string, results: list<array<string, mixed>>, meta: array{total: int, per_type: array<string, int>}}
     */
    public function search(
        User $user,
        string $query,
        ?array $types = null,
        int $limit = 5,
        bool $includeCompleted = false,
    ): array {
        $providers = $this->resolveProviders($user, $types);

        if ($providers->isEmpty()) {
            return [
                'query' => $query,
                'results' => [],
                'meta' => ['total' => 0, 'per_type' => []],
            ];
        }

        if (config('scout.driver') === 'meilisearch') {
            return $this->searchViaMultiSearch($providers, $query, $limit, $includeCompleted);
        }

        return $this->searchViaFallback($providers, $query, $limit, $includeCompleted);
    }

    /**
     * @param  Collection<int, SearchProvider>  $providers
     * @return array{query: string, results: list<array<string, mixed>>, meta: array{total: int, per_type: array<string, int>}}
     */
    private function searchViaMultiSearch(Collection $providers, string $query, int $limit, bool $includeCompleted): array
    {
        $providerByIndex = $providers->keyBy(fn (SearchProvider $provider) => $provider->index());

        $queries = $providers
            ->map(fn (SearchProvider $provider) => $provider->buildSearchQuery($query, $limit, $includeCompleted))
            ->values()
            ->all();

        $response = $this->meilisearch->multiSearch($queries);
        $results = collect();
        $perType = [];

        foreach ($response['results'] ?? [] as $indexResult) {
            $indexUid = $indexResult['indexUid'] ?? null;
            $provider = $indexUid ? $providerByIndex->get($indexUid) : null;

            if (! $provider) {
                continue;
            }

            $hits = collect($indexResult['hits'] ?? [])
                ->map(fn (array $hit) => $provider->mapHit($hit));

            $perType[$provider->type()] = $hits->count();
            $results = $results->merge($hits);
        }

        return [
            'query' => $query,
            'results' => $results->values()->all(),
            'meta' => [
                'total' => $results->count(),
                'per_type' => $perType,
            ],
        ];
    }

    /**
     * @param  Collection<int, SearchProvider>  $providers
     * @return array{query: string, results: list<array<string, mixed>>, meta: array{total: int, per_type: array<string, int>}}
     */
    private function searchViaFallback(Collection $providers, string $query, int $limit, bool $includeCompleted): array
    {
        $results = collect();
        $perType = [];

        foreach ($providers as $provider) {
            if (! $provider instanceof AbstractSearchProvider) {
                continue;
            }

            $hits = collect($provider->searchFallback($query, $limit, $includeCompleted));
            $perType[$provider->type()] = $hits->count();
            $results = $results->merge($hits);
        }

        return [
            'query' => $query,
            'results' => $results->values()->all(),
            'meta' => [
                'total' => $results->count(),
                'per_type' => $perType,
            ],
        ];
    }

    /**
     * @param  list<string>|null  $types
     * @return Collection<int, SearchProvider>
     */
    private function resolveProviders(User $user, ?array $types): Collection
    {
        return collect($this->providers)
            ->filter(fn (SearchProvider $provider) => $provider->isAvailableFor($user))
            ->when(
                $types !== null && $types !== [],
                fn (Collection $providers) => $providers->filter(
                    fn (SearchProvider $provider) => in_array($provider->type(), $types, true)
                )
            )
            ->values();
    }
}
