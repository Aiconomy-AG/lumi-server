<?php

namespace Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Workspace\Http\Requests\StoreMessageRequest;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Message;
use Modules\Workspace\Services\NotificationService;
use Modules\Workspace\Transformers\MessageResource;

class MessageController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Display a paginated listing of the conversation's messages.
     */
    public function index(Request $request, int $conversationId)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $afterId = (int) $request->query('after_id', 0);

        $query = Message::query()
            ->where('conversation_id', $conversationId);

        if ($afterId > 0) {
            $messages = $query
                ->where('id', '>', $afterId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit($perPage)
                ->get();

            return MessageResource::collection($messages);
        }

        $messages = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($perPage)
            ->get()
            ->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        return MessageResource::collection($messages);
    }

    /**
     * Store a newly sent message.
     */
    public function store(StoreMessageRequest $request, int $conversationId)
    {
        $conversation = Conversation::query()
            ->with('participants')
            ->findOrFail($conversationId);

        $message = Message::create([
            'conversation_id' => $conversationId,
            'sender_id' => $request->user()->id,
            'message' => $request->validated('message'),
        ]);

        $recipientIds = $conversation->participants
            ->pluck('id')
            ->filter(fn (int $userId) => $userId !== (int) $request->user()->id)
            ->values()
            ->all();

        $this->notificationService->createForRecipients(
            type: 'chat_message_received',
            source: 'chat',
            recipientUserIds: $recipientIds,
            actorUserId: (int) $request->user()->id,
            conversationId: $conversation->id,
            messageId: $message->id,
            payload: [
                'conversation_name' => $conversation->name,
                'conversation_type' => $conversation->type,
                'message_preview' => str($message->message)->limit(120)->toString(),
            ],
        );

        return (new MessageResource($message))
            ->response()
            ->setStatusCode(201);
    }
}
