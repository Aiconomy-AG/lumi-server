<?php

namespace Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Workspace\Domain\Calls\LiveKitWebhookException;
use Modules\Workspace\Services\CallWebhookService;

class LiveKitWebhookController extends Controller
{
    public function __construct(
        private readonly CallWebhookService $webhooks,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->webhooks->handle(
                $request->getContent(),
                $request->header('Authorization'),
            );
        } catch (LiveKitWebhookException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode);
        }

        return response()->json(['ok' => true]);
    }
}
