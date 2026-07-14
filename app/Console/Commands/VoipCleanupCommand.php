<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use Illuminate\Console\Command;
use Modules\Workspace\Domain\Calls\CallStatus;
use Modules\Workspace\Models\Call;
use Modules\Workspace\Models\CallEvent;
use Modules\Workspace\Services\CallService;

class VoipCleanupCommand extends Command
{
    protected $signature = 'voip:cleanup';

    protected $description = 'Prune stale VoIP device tokens, orphaned ringing calls, and old call events.';

    public function handle(CallService $calls): int
    {
        $tokenDays = (int) config('voip.cleanup.device_token_days', 90);
        $ringingMinutes = (int) config('voip.cleanup.orphaned_ringing_minutes', 5);
        $eventDays = (int) config('voip.cleanup.call_events_days', 90);

        $tokensDeleted = DeviceToken::query()
            ->where('updated_at', '<', now()->subDays($tokenDays))
            ->delete();

        $callsExpired = 0;
        Call::query()
            ->where('status', CallStatus::Ringing->value)
            ->where('created_at', '<', now()->subMinutes($ringingMinutes))
            ->orderBy('id')
            ->chunkById(100, function ($staleCalls) use ($calls, &$callsExpired): void {
                foreach ($staleCalls as $call) {
                    if ($calls->markMissed($call->id) !== null) {
                        $callsExpired++;
                    }
                }
            }, 'id');

        $eventsDeleted = CallEvent::query()
            ->where('created_at', '<', now()->subDays($eventDays))
            ->delete();

        $this->info("Pruned {$tokensDeleted} device tokens, expired {$callsExpired} ringing calls, deleted {$eventsDeleted} call events.");

        return self::SUCCESS;
    }
}
