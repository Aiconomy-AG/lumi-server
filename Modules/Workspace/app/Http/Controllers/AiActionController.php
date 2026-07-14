<?php

namespace Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Workspace\Models\AiAction;
use Modules\Workspace\Services\AiActionService;
use Modules\Workspace\Transformers\AiActionResource;

class AiActionController extends Controller
{
    public function __construct(
        private readonly AiActionService $aiActionService,
    ) {}

    public function approve(int $conversationId, int $actionId): JsonResponse
    {
        $action = AiAction::query()->findOrFail($actionId);

        $action = $this->aiActionService->approve(
            $action,
            request()->user(),
            $conversationId,
        );

        return (new AiActionResource($action))
            ->response()
            ->setStatusCode(200);
    }

    public function reject(int $conversationId, int $actionId): JsonResponse
    {
        $action = AiAction::query()->findOrFail($actionId);

        $action = $this->aiActionService->reject(
            $action,
            request()->user(),
            $conversationId,
        );

        return (new AiActionResource($action))
            ->response()
            ->setStatusCode(200);
    }
}
