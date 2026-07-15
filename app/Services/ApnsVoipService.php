<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Services\Push\ApnsVoipPushService;
use App\Support\DeviceTokenPlatform;
use Illuminate\Support\Facades\Log;

class ApnsVoipService
{
    public function __construct(
        private readonly ApnsVoipPushService $apns,
    ) {}

    public function isConfigured(): bool
    {
        return $this->apns->isConfigured();
    }

    public function sendVoipToUser(int $userId, array $data): bool
    {
        if (! $this->isConfigured()) {
            Log::info('APNs VoIP push skipped because APNs is not configured.', [
                'user_id' => $userId,
            ]);

            return false;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $userId)
            ->where('platform', DeviceTokenPlatform::VOIP_IOS)
            ->whereNull('invalidated_at')
            ->get(['id', 'token']);

        if ($tokens->isEmpty()) {
            return false;
        }

        $sent = false;
        foreach ($tokens as $token) {
            if ($this->sendVoipToken($token->token, $data)) {
                $sent = true;
            }
        }

        return $sent;
    }

    public function sendVoipToken(string $token, array $data): bool
    {
        try {
            $result = $this->apns->sendIncomingCall($token, $data, $data['call_id'] ?? null);
            if ($result->shouldInvalidateToken) {
                DeviceToken::query()
                    ->where('token', $token)
                    ->update(['invalidated_at' => now()]);
            }

            return $result->success;
        } catch (\Throwable $exception) {
            Log::warning('APNs VoIP push failed.', [
                'token' => $this->apns->tokenFingerprint($token),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
