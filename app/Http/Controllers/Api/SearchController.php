<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly GlobalSearchService $searchService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'types' => ['sometimes', 'array'],
            'types.*' => ['string', 'in:task,project,product,order,return,user'],
            'include_completed' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $result = $this->searchService->search(
            $request->user(),
            $validated['q'],
            $validated['types'] ?? null,
            $validated['limit'] ?? 5,
            (bool) ($validated['include_completed'] ?? false),
        );

        return response()->json(['data' => $result]);
    }
}
