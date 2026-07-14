<?php

namespace Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Workspace\Services\CallWebhookService;

class LiveKitWebhookController extends Controller
{
    public function __construct(
        private readonly CallWebhookService $webhooks,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->webhooks->handle(
            $request->getContent(),
            $request->header('Authorization'),
        );

        return response()->json(['ok' => true]);
    }
}
