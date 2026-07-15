<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Services\Push\ApnsVoipPushService;
use App\Support\DeviceTokenPlatform;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ApnsTestVoipCommand extends Command
{
    protected $signature = 'apns:test-voip {userId} {--call-id=} {--caller-name=Sandbox Test} {--call-type=video} {--call-mode=1v1}';

    protected $description = 'Send a sandbox APNs VoIP test push to the latest active iOS VoIP token for a user.';

    public function handle(ApnsVoipPushService $apns): int
    {
        $callType = (string) $this->option('call-type');
        $callMode = (string) $this->option('call-mode');
        if (! in_array($callType, ['audio', 'video'], true) || ! in_array($callMode, ['1v1', 'group'], true)) {
            $this->error('Invalid --call-type or --call-mode.');

            return self::INVALID;
        }

        $token = DeviceToken::query()
            ->where('user_id', (int) $this->argument('userId'))
            ->where('platform', DeviceTokenPlatform::VOIP_IOS)
            ->whereNull('invalidated_at')
            ->latest('updated_at')
            ->first();

        if (! $token) {
            $this->error('No active voip_ios token found for this user.');

            return self::FAILURE;
        }

        $payload = [
            'type' => 'workspace_call_incoming',
            'call_id' => (string) ($this->option('call-id') ?: 'test-'.Str::uuid()),
            'caller_name' => (string) $this->option('caller-name'),
            'caller_user_id' => '1',
            'call_type' => $callType,
            'call_mode' => $callMode,
            'conversation_id' => '1',
            'status' => 'ringing',
        ];

        $result = $apns->sendIncomingCall($token->token, $payload, $payload['call_id']);

        if ($result->shouldInvalidateToken) {
            $token->update(['invalidated_at' => now()]);
        }

        $this->info('APNs status: '.$result->statusCode);
        $this->info('APNs reason: '.($result->reason ?: 'none'));

        return $result->success ? self::SUCCESS : self::FAILURE;
    }
}
