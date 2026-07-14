<?php

namespace Modules\Workspace\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Workspace\Events\MessageSent;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Message;
use Modules\Workspace\Services\AiActionService;
use Modules\Workspace\Services\ChatAiUserResolver;
use Modules\Workspace\Services\GeminiChatService;
use Modules\Workspace\Support\ChatMentionDetector;
use Throwable;

class GenerateAiChatReplyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $messageId
    ) {}

    public function handle(
        ChatAiUserResolver $botResolver,
        GeminiChatService $geminiChatService,
        AiActionService $aiActionService,
    ): void {
        try {
            $this->run($botResolver, $geminiChatService, $aiActionService);
        } catch (Throwable $e) {
            Log::error('AI chat reply job failed', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);

            $this->postErrorIfPossible($botResolver, $this->messageId, $e->getMessage());
        }
    }

    private function run(
        ChatAiUserResolver $botResolver,
        GeminiChatService $geminiChatService,
        AiActionService $aiActionService,
    ): void {
        if (! $botResolver->isEnabled()) {
            return;
        }

        $bot = $botResolver->botUser();
        if ($bot === null) {
            $this->postErrorIfPossible($botResolver, $this->messageId, 'Bot user not found. Run AiAssistantUserSeeder.');

            return;
        }

        $triggerMessage = Message::query()->find($this->messageId);
        if ($triggerMessage === null) {
            return;
        }

        if ($botResolver->isBotUser($triggerMessage->sender_id)) {
            return;
        }

        if (! ChatMentionDetector::isMentioned($triggerMessage->message)) {
            return;
        }

        $actingUser = User::query()->find($triggerMessage->sender_id);
        if ($actingUser === null) {
            return;
        }

        $rateLimitKey = 'ai-chat:'.$actingUser->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $this->postBotMessage(
                $triggerMessage->conversation_id,
                $bot->id,
                '[Lumi AI error] Rate limit exceeded. Please wait a minute and try again.',
            );

            return;
        }
        RateLimiter::hit($rateLimitKey, 60);

        $conversation = Conversation::query()
            ->with('participants')
            ->find($triggerMessage->conversation_id);

        if ($conversation === null) {
            return;
        }

        if (! $conversation->participants->contains('id', $bot->id)) {
            $conversation->participants()->attach($bot->id);
        }

        $historyLimit = (int) config('chat_ai.history_limit', 20);
        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($historyLimit)
            ->get()
            ->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $latestPrompt = ChatMentionDetector::stripMentions($triggerMessage->message);
        if ($latestPrompt === '') {
            $latestPrompt = 'Hello';
        }

        $result = $geminiChatService->generateReply(
            $messages,
            $latestPrompt,
            $triggerMessage->id,
            $actingUser,
            $conversation,
        );

        if ($result->hasProposedAction()) {
            $action = $aiActionService->createPending(
                $conversation,
                $actingUser,
                $result->proposedAction,
            );

            if ($action->message) {
                $this->broadcastMessage($action->message);
            }

            return;
        }

        $replyText = $result->replyText();
        if ($replyText === null || $replyText === '') {
            $this->postBotMessage(
                $conversation->id,
                $bot->id,
                '[Lumi AI error] No response was generated.',
            );

            return;
        }

        $this->postBotMessage($conversation->id, $bot->id, $replyText);
    }

    private function postErrorIfPossible(ChatAiUserResolver $botResolver, int $messageId, string $error): void
    {
        $bot = $botResolver->botUser();
        $triggerMessage = Message::query()->find($messageId);

        if ($bot === null || $triggerMessage === null) {
            return;
        }

        $this->postBotMessage(
            $triggerMessage->conversation_id,
            $bot->id,
            '[Lumi AI error] '.str($error)->limit(900)->toString(),
        );
    }

    private function postBotMessage(int $conversationId, int $botUserId, string $text, string $type = 'text', ?array $meta = null): void
    {
        $botMessage = Message::create([
            'conversation_id' => $conversationId,
            'sender_id' => $botUserId,
            'message' => $text,
            'type' => $type,
            'meta' => $meta,
        ]);

        $this->broadcastMessage($botMessage);
    }

    private function broadcastMessage(Message $message): void
    {
        try {
            MessageSent::dispatch($message);
        } catch (Throwable $e) {
            Log::warning('Message broadcast failed', [
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
