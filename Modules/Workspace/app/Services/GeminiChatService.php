<?php

namespace Modules\Workspace\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Workspace\Models\Message;

class GeminiChatService
{
    public function __construct(
        private readonly ChatAiUserResolver $botResolver
    ) {}

    /**
     * @param  Collection<int, Message>  $messages
     */
    public function generateReply(Collection $messages, string $latestPrompt, int $triggerMessageId): ?string
    {
        $apiKey = config('chat_ai.gemini_api_key');
        $model = config('chat_ai.gemini_model');

        if (! filled($apiKey)) {
            return null;
        }

        $botUserId = $this->botResolver->botUser()?->id;
        $contents = [];

        foreach ($messages as $message) {
            if ($message->message === '') {
                continue;
            }

            $role = $message->sender_id === $botUserId ? 'model' : 'user';
            $text = $message->id === $triggerMessageId && $role === 'user'
                ? $latestPrompt
                : $message->message;

            if ($text === '') {
                continue;
            }

            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $text]],
            ];
        }

        if ($contents === []) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $latestPrompt]],
            ];
        }

        $response = Http::timeout(30)
            ->withQueryParameters(['key' => $apiKey])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'system_instruction' => [
                    'parts' => [[
                        'text' => 'You are Lumi AI, a helpful assistant in a team workspace chat. '
                            .'Answer concisely in the same language the user writes in. '
                            .'Be friendly and practical. Do not pretend to be human.',
                    ]],
                ],
                'contents' => $contents,
            ]);

        if (! $response->successful()) {
            Log::warning('Gemini chat request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $parts = data_get($response->json(), 'candidates.0.content.parts', []);

        $text = collect(is_array($parts) ? $parts : [])
            ->reject(fn ($part) => ($part['thought'] ?? false) === true)
            ->pluck('text')
            ->filter(fn ($value) => is_string($value))
            ->implode("\n");

        if (trim($text) === '') {
            return null;
        }

        return trim($text);
    }
}
