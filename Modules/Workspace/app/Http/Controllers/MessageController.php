<?php

namespace Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotificationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Modules\Workspace\Events\MessageReactionUpdated;
use Modules\Workspace\Events\MessageSent;
use Modules\Workspace\Http\Requests\StoreMessageReactionRequest;
use Modules\Workspace\Http\Requests\StoreMessageRequest;
use Modules\Workspace\Jobs\GenerateAiChatReplyJob;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Message;
use Modules\Workspace\Services\ChatAiUserResolver;
use Modules\Workspace\Services\NotificationService;
use Modules\Workspace\Support\ChatMentionDetector;
use Modules\Workspace\Transformers\MessageResource;

class MessageController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly ChatAiUserResolver $chatAiUserResolver,
    ) {}

    public function index(Request $request, int $conversationId)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $afterId = (int) $request->query('after_id', 0);

        $query = Message::query()
            ->with(['call', 'reactions'])
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

        foreach ($recipientIds as $recipientId) {
            SendPushNotificationJob::dispatch(
                $recipientId,
                'New message',
                'You received a new message',
                [
                    'type' => 'chat_message_received',
                    'conversation_id' => (string) $conversation->id,
                    'message_id' => (string) $message->id,
                ],
            );
        }

        if (
            $this->chatAiUserResolver->isEnabled()
            && ! $this->chatAiUserResolver->isBotUser((int) $request->user()->id)
            && ChatMentionDetector::isMentioned($message->message)
        ) {
            GenerateAiChatReplyJob::dispatch($message->id);
        }

        try {
            MessageSent::dispatch($message->load('reactions'));
        } catch (\Throwable $e) {
            report($e);
        }

        return (new MessageResource($message))
            ->response()
            ->setStatusCode(201);
    }

    public function react(StoreMessageReactionRequest $request, int $conversationId, int $messageId): JsonResource|JsonResponse
    {
        $message = $this->findConversationMessage($conversationId, $messageId);

        if (! $message) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Message not found.'], 404);
        }

        DB::transaction(function () use ($message, $request): void {
            $emoji = $request->validated('emoji');

            $message->reactions()
                ->where('user_id', $request->user()->id)
                ->where('emoji', '!=', $emoji)
                ->delete();

            $message->reactions()->firstOrCreate([
                'user_id' => $request->user()->id,
                'emoji' => $emoji,
            ]);
        });

        return $this->reactionResponse($message);
    }

    public function unreact(StoreMessageReactionRequest $request, int $conversationId, int $messageId): JsonResource|JsonResponse
    {
        $message = $this->findConversationMessage($conversationId, $messageId);

        if (! $message) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Message not found.'], 404);
        }

        $message->reactions()
            ->where('user_id', $request->user()->id)
            ->delete();

        return $this->reactionResponse($message);
    }

    private function findConversationMessage(int $conversationId, int $messageId): ?Message
    {
        return Message::query()
            ->where('conversation_id', $conversationId)
            ->whereKey($messageId)
            ->first();
    }

    private function reactionResponse(Message $message): JsonResource
    {
        $message = $message->fresh(['call', 'reactions']);

        try {
            MessageReactionUpdated::dispatch($message);
        } catch (\Throwable $e) {
            report($e);
        }

        return new MessageResource($message);
    }
}
