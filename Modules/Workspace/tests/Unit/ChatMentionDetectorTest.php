<?php

namespace Modules\Workspace\Tests\Unit;

use Modules\Workspace\Support\ChatMentionDetector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatMentionDetectorTest extends TestCase
{
    #[Test]
    public function it_detects_lumi_and_ai_mentions_case_insensitively(): void
    {
        $this->assertTrue(ChatMentionDetector::isMentioned('@lumi what is the stock?'));
        $this->assertTrue(ChatMentionDetector::isMentioned('Hey @Lumi'));
        $this->assertTrue(ChatMentionDetector::isMentioned('@ai help me'));
        $this->assertTrue(ChatMentionDetector::isMentioned('cc @AI please'));
    }

    #[Test]
    public function it_ignores_non_mentions(): void
    {
        $this->assertFalse(ChatMentionDetector::isMentioned('hello team'));
        $this->assertFalse(ChatMentionDetector::isMentioned('@luminary event'));
        $this->assertFalse(ChatMentionDetector::isMentioned('email ai@example.com'));
    }

    #[Test]
    public function it_strips_mentions_from_text(): void
    {
        $this->assertSame(
            'what is the stock?',
            ChatMentionDetector::stripMentions('@lumi what is the stock?')
        );

        $this->assertSame(
            'help me',
            ChatMentionDetector::stripMentions('@ai help me')
        );
    }
}
