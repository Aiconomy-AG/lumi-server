<?php

namespace Modules\Workspace\Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Modules\Workspace\AiTools\Read\GetCurrentTimeTool;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GetCurrentTimeToolTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_current_time_in_workspace_timezone(): void
    {
        Config::set('chat_ai.workspace_timezone', 'Europe/Bucharest');

        Carbon::setTestNow(Carbon::parse('2026-07-14 16:30:00', 'Europe/Bucharest'));

        $user = User::factory()->create();
        $result = app(GetCurrentTimeTool::class)->execute($user, []);

        $this->assertSame('Europe/Bucharest', $result['timezone']);
        $this->assertSame('2026-07-14', $result['date']);
        $this->assertSame('16:30', $result['time']);
        $this->assertSame('Tuesday', $result['day_of_week']);
        $this->assertSame(16, $result['hour']);
        $this->assertFalse($result['is_weekend']);

        Carbon::setTestNow();
    }
}
