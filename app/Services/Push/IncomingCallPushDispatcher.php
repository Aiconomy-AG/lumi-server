<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\PushNotificationService;
use App\Support\DeviceTokenPlatform;
use Illuminate\Support\Facades\Log;
use Modules\Workspace\Models\Call;

class IncomingCallPushDispatcher
{
    public function __construct(
        private readonly PushNotificationService $fcm,
        private readonly ApnsVoipPushService $apns,
    ) {}

    public function dispatchIncomingCall(Call $call, User $callee): void
    {
        $payload = $this->payload($call);

        Log::info('incoming_call_push.dispatch', [
            'user_id' => (int) $callee->id,
            'call_id' => $call->id,
        ]);

        DeviceToken::query()
            ->where('user_id', $callee->id)
            ->whereNull('invalidated_at')
            ->orderBy('id')
            ->get()
            ->each(function (DeviceToken $deviceToken) use ($payload, $call, $callee): void {
                if (in_array($deviceToken->platform, DeviceTokenPlatform::fcmPlatforms(), true)) {
                    $this->fcm->sendCallEventToToken($deviceToken->token, 'Incoming Lumi call', $call->caller_name.' is calling', $payload);

                    return;
                }

                if ($deviceToken->platform === DeviceTokenPlatform::VOIP_IOS) {
                    $result = $this->apns->sendIncomingCall($deviceToken->token, $payload, (string) $call->id);

                    Log::log($result->success ? 'info' : 'warning', $result->success ? 'incoming_call_push.apns_success' : 'incoming_call_push.apns_failure', [
                        'user_id' => (int) $callee->id,
                        'device_id' => $deviceToken->device_id,
                        'platform' => $deviceToken->platform,
                        'call_id' => $call->id,
                        'apns_status' => $result->statusCode,
                        'apns_reason' => $result->reason,
                    ]);

                    if ($result->shouldInvalidateToken) {
                        $deviceToken->update(['invalidated_at' => now()]);
                        Log::info('incoming_call_push.token_invalidated', [
                            'user_id' => (int) $callee->id,
                            'device_id' => $deviceToken->device_id,
                            'platform' => $deviceToken->platform,
                            'call_id' => $call->id,
                            'apns_status' => $result->statusCode,
                            'apns_reason' => $result->reason,
                        ]);
                    }
                }
            });
    }

    private function payload(Call $call): array
    {
        return [
            'type' => 'workspace_call_incoming',
            'call_id' => (string) $call->id,
            'caller_name' => (string) $call->caller_name,
            'caller_user_id' => (string) $call->initiated_by_user_id,
            'call_type' => $call->callTypeValue(),
            'call_mode' => $call->callModeValue(),
            'conversation_id' => (string) ($call->conversation_id ?? ''),
            'status' => 'ringing',
        ];
    }
}
