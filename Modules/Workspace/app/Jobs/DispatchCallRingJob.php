<?php

namespace Modules\Workspace\Jobs;

use App\Services\Push\IncomingCallPushDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Workspace\Events\CallIncoming;
use Modules\Workspace\Events\CallRinging;
use Modules\Workspace\Models\Call;
use Modules\Workspace\Services\CallEventLogger;

class DispatchCallRingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $callId,
        public readonly array $inviteeUserIds = [],
    ) {
        $this->onQueue((string) config('voip.queues.calls', 'calls'));
    }

    public function handle(
        IncomingCallPushDispatcher $pushes,
        CallEventLogger $events,
    ): void {
        $call = Call::query()
            ->with(['participants.user', 'conversation'])
            ->find($this->callId);

        if (! $call) {
            return;
        }

        if (! in_array($call->status->value, ['ringing', 'active'], true)) {
            return;
        }

        $invitees = $this->inviteeUserIds !== []
            ? $call->participants->whereIn('user_id', $this->inviteeUserIds)
            : $call->participants->filter(fn ($participant) => $participant->role === 'callee');

        $invitees = $invitees->filter(fn ($participant) => $participant->isPending());

        foreach ($invitees as $participant) {
            $userId = (int) $participant->user_id;

            CallIncoming::dispatch($call, $userId);
            CallRinging::dispatch($call, $userId);

            $pushes->dispatchIncomingCall($call, $participant->user);

            $participant->update(['ringing_delivered_at' => now()]);
            $events->logCall($call, 'rung', [
                'user_id' => $userId,
                'channels' => ['reverb', 'fcm_android', 'voip_ios'],
            ]);
        }
    }
}
