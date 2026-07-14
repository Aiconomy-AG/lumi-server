<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Support\DeviceTokenPlatform;
use Illuminate\Support\Facades\Log;
use Pushok\AuthProvider;
use Pushok\Client;
use Pushok\Notification;
use Pushok\Payload;

class ApnsVoipService
{
    public function isConfigured(): bool
    {
        return (string) config('voip.apns.key_id') !== ''
            && (string) config('voip.apns.team_id') !== ''
            && (string) config('voip.apns.bundle_id') !== ''
            && (string) config('voip.apns.key_path') !== ''
            && is_readable((string) config('voip.apns.key_path'));
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
            ->where('platform', DeviceTokenPlatform::APNS_VOIP)
            ->pluck('token');

        if ($tokens->isEmpty()) {
            return false;
        }

        $sent = false;
        foreach ($tokens as $token) {
            if ($this->sendVoipToken($token, $data)) {
                $sent = true;
            }
        }

        return $sent;
    }

    public function sendVoipToken(string $token, array $data): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $authProvider = AuthProvider\Token::create([
                'key_id' => config('voip.apns.key_id'),
                'team_id' => config('voip.apns.team_id'),
                'app_bundle_id' => config('voip.apns.bundle_id'),
                'private_key_path' => config('voip.apns.key_path'),
                'private_key_secret' => null,
            ]);

            $client = new Client($authProvider, (bool) config('voip.apns.production', false));
            $payload = Payload::create()->setCustomValue('lumi', $data);
            $notification = new Notification($payload, $token);
            $notification->setPushType('voip');
            $notification->setPriority(10);

            $client->addNotification($notification);
            $responses = $client->push();

            foreach ($responses as $response) {
                if ($response->getStatusCode() >= 400) {
                    if (in_array($response->getStatusCode(), [400, 410], true)) {
                        DeviceToken::query()->where('token', $token)->delete();
                    }

                    Log::warning('APNs VoIP push failed.', [
                        'token' => $token,
                        'status' => $response->getStatusCode(),
                        'reason' => $response->getReasonPhrase(),
                    ]);

                    return false;
                }
            }

            return true;
        } catch (\Throwable $exception) {
            Log::warning('APNs VoIP push failed.', [
                'token' => $token,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
