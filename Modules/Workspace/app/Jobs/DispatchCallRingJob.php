<?php

namespace Modules\Workspace\Jobs;

use App\Services\ApnsVoipService;
use App\Services\PushNotificationService;
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
        PushNotificationService $push,
        ApnsVoipService $apns,
        CallEventLogger $events,
    ): void {
        $call = Call::query()
            ->with(['participants.user', 'conversation'])
            ->find($this->callId);

        if (! $call) {
            return;
        }

        $invitees = $this->inviteeUserIds !== []
            ? $call->participants->whereIn('user_id', $this->inviteeUserIds)
            : $call->participants->filter(fn ($participant) => $participant->role === 'callee');

        foreach ($invitees as $participant) {
            $userId = (int) $participant->user_id;

            CallIncoming::dispatch($call, $userId);
            CallRinging::dispatch($call, $userId);

            $payload = $this->payload($call);
            $title = 'Incoming Lumi call';
            $body = $call->caller_name.' is calling';

            $push->sendCallEventToUser($userId, $title, $body, $payload);
            $push->sendCallAlertToUser($userId, $title, $body, $payload);
            $apnsSent = $apns->sendVoipToUser($userId, $payload);

            $participant->update(['ringing_delivered_at' => now()]);
            $events->logCall($call, $apnsSent ? 'push_sent' : 'rung', [
                'user_id' => $userId,
                'channels' => ['reverb', 'fcm', 'apns_voip'],
            ]);
        }
    }

    private function payload(Call $call): array
    {
        return [
            'type' => 'workspace_call_incoming',
            'call_id' => $call->id,
            'room_name' => $call->room_name,
            'conversation_id' => (string) ($call->conversation_id ?? ''),
            'status' => $call->status->value,
            'destination_type' => $call->destination_type,
            'caller_user_id' => (string) $call->initiated_by_user_id,
            'caller_name' => $call->caller_name,
            'call_type' => $call->callTypeValue(),
            'call_mode' => $call->callModeValue(),
        ];
    }
}
