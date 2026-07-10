<?php

namespace Modules\Workspace\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Modules\Workspace\Events\MessageSent;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Message;
use Modules\Workspace\Services\ChatAiUserResolver;
use Modules\Workspace\Services\GeminiChatService;
use Modules\Workspace\Support\ChatMentionDetector;

class GenerateAiChatReplyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $messageId
    ) {}

    public function handle(
        ChatAiUserResolver $botResolver,
        GeminiChatService $geminiChatService
    ): void {
        if (! $botResolver->isEnabled()) {
            return;
        }

        $bot = $botResolver->botUser();
        if ($bot === null) {
            Log::warning('Chat AI is enabled but bot user was not found. Run AiAssistantUserSeeder.');

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

        $reply = $geminiChatService->generateReply(
            $messages,
            $latestPrompt,
            $triggerMessage->id
        );

        if ($reply === null || $reply === '') {
            return;
        }

        $botMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $bot->id,
            'message' => $reply,
        ]);

        MessageSent::dispatch($botMessage);
    }
}
