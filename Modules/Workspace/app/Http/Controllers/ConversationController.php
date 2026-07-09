<?php

namespace Modules\Workspace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Workspace\Http\Requests\StoreConversationRequest;
use Modules\Workspace\Services\ConversationService;
use Modules\Workspace\Transformers\ConversationResource;

class ConversationController
{
    public function __construct(
        private readonly ConversationService $conversationService
    )
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $conversations = $this->conversationService->getAllForUser($request->user()->id);

        return ConversationResource::collection($conversations);
    }

    public function store(StoreConversationRequest $request): ConversationResource
    {
        $conversation = $this->conversationService->create(
            $request->validated(),
            $request->user()->id
        );

        return new ConversationResource($conversation);
    }

    public function show(int $conversationId): ConversationResource|JsonResponse
    {
        $conversation = $this->conversationService->getById($conversationId);
        if(!$conversation){
            return response()->json(['code' => 'NOT_FOUND','message' => 'Conversation not foound.'], 404);

        }
        return new ConversationResource($conversation);
    }
}
