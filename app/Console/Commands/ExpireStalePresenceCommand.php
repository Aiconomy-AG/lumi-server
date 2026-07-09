<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PresenceService;
use Illuminate\Console\Command;

class ExpireStalePresenceCommand extends Command
{
    protected $signature = 'presence:expire-stale';

    protected $description = 'Mark users as offline when presence heartbeat is stale.';

    public function handle(PresenceService $presenceService): int
    {
        $ttlSeconds = max((int) config('presence.offline_ttl_seconds', 90), 1);
        $staleBefore = now()->subSeconds($ttlSeconds);

        User::query()
            ->whereIn('status', ['available', 'busy', 'away'])
            ->where(function ($query) use ($staleBefore) {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $staleBefore);
            })
            ->chunkById(200, function ($users) use ($presenceService) {
                foreach ($users as $user) {
                    $presenceService->markOffline($user);
                }
            });

        return self::SUCCESS;
    }
}
