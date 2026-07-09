<?php

namespace Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
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

        return (new MessageResource($message))
            ->response()
            ->setStatusCode(201);
    }
}