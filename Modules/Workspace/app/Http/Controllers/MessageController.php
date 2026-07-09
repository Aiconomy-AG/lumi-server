<?php

namespace Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
<<<<<<< Updated upstream
=======
use Illuminate\Http\Request;
use Modules\Workspace\Events\MessageSent;
>>>>>>> Stashed changes
use Modules\Workspace\Http\Requests\StoreMessageRequest;
use Modules\Workspace\Models\Message;
use Modules\Workspace\Transformers\MessageResource;

class MessageController extends Controller
{
    /**
     * Display a paginated listing of the conversation's messages.
     */
    public function index(int $conversationId)
    {
        $messages = Message::where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(50);

        return MessageResource::collection($messages);
    }

    /**
     * Store a newly sent message.
     */
    public function store(StoreMessageRequest $request, int $conversationId)
    {
        $message = Message::create([
            'conversation_id' => $conversationId,
            'sender_id' => $request->user()->id,
            'message' => $request->validated('message'),
        ]);

<<<<<<< Updated upstream
=======
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

        MessageSent::dispatch($message);

>>>>>>> Stashed changes
        return (new MessageResource($message))
            ->response()
            ->setStatusCode(201);
    }
}