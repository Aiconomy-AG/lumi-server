<?php

namespace Modules\Workspace\Support;

class ChatMentionDetector
{
    public static function isMentioned(string $text): bool
    {
        return (bool) preg_match('/@(?:lumi|ai)\b/i', $text);
    }

    public static function stripMentions(string $text): string
    {
        $stripped = preg_replace('/@(?:lumi|ai)\b/i', '', $text);

        return trim(preg_replace('/\s+/', ' ', (string) $stripped));
    }
}
