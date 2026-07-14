<?php

namespace Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Workspace\Http\Requests\StoreConversationRequest;
use Modules\Workspace\Http\Requests\UpdateConversationRequest;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Services\ConversationService;
use Modules\Workspace\Transformers\ConversationResource;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService
    )
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Conversation::class);

        $conversations = $this->conversationService->getAllForUser($request->user()->id);

        return ConversationResource::collection($conversations);
    }

    public function store(StoreConversationRequest $request): ConversationResource
    {
        $this->authorize('create', Conversation::class);

        $conversation = $this->conversationService->create(
            $request->validated(),
            $request->user()->id
        );

        AuditLog::record(
            module: 'workspace',
            action: 'conversation_create',
            entity: $conversation,
            label: $conversation->name ?: 'Conversation #'.$conversation->id,
            changes: ['new' => ['type' => $conversation->type, 'name' => $conversation->name]],
        );

        return new ConversationResource($conversation);
    }

    public function show(int $conversationId): ConversationResource|JsonResponse
    {
        $conversation = $this->conversationService->getById($conversationId);
        if(!$conversation){
            return response()->json(['code' => 'NOT_FOUND','message' => 'Conversation not foound.'], 404);

        }

        $this->authorize('view', $conversation);

        return new ConversationResource($conversation);
    }

    public function update(UpdateConversationRequest $request, int $conversationId): ConversationResource|JsonResponse
    {
        $conversation = $this->conversationService->getById($conversationId);

        if (! $conversation) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Conversation not foound.'], 404);
        }

        $this->authorize('update', $conversation);

        if ($conversation->type !== 'group') {
            return response()->json(['code' => 'INVALID', 'message' => 'Only group conversations can be updated.'], 422);
        }

        $validated = $request->validated();
        if (
            ! array_key_exists('name', $validated)
            && ! array_key_exists('add_participants_employee_ids', $validated)
            && ! array_key_exists('remove_participants_employee_ids', $validated)
        ) {
            return response()->json(['code' => 'INVALID', 'message' => 'No updates were provided.'], 422);
        }

        try {
            $conversation = $this->conversationService->update(
                $conversation,
                $validated,
                $request->user()->id
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['code' => 'INVALID', 'message' => $exception->getMessage()], 422);
        }

        AuditLog::record(
            module: 'workspace',
            action: 'conversation_update',
            entity: $conversation,
            label: $conversation->name ?: 'Conversation #'.$conversation->id,
            changes: ['new' => $validated],
        );

        return new ConversationResource($conversation);
    }
}
