<?php

namespace Modules\Workspace\Services;

use App\Models\User;

class ChatAiUserResolver
{
    public function isEnabled(): bool
    {
        return (bool) config('chat_ai.enabled')
            && filled(config('chat_ai.gemini_api_key'));
    }

    public function botUser(): ?User
    {
        return User::query()
            ->where('email', config('chat_ai.user_email'))
            ->where('is_active', true)
            ->first();
    }

    public function isBotUser(int $userId): bool
    {
        $bot = $this->botUser();

        return $bot !== null && $bot->id === $userId;
    }

    public function isBotEmail(?string $email): bool
    {
        return $email !== null && strcasecmp($email, (string) config('chat_ai.user_email')) === 0;
    }
}
