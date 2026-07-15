<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class ApnsVoipPushService
{
    public function __construct(
        private readonly ApnsJwtFactory $jwtFactory,
    ) {}

    public function isConfigured(): bool
    {
        return (string) config('apns.p8_path') !== ''
            && is_readable((string) config('apns.p8_path'))
            && (string) config('apns.key_id') !== ''
            && (string) config('apns.team_id') !== ''
            && (string) config('apns.bundle_id') !== '';
    }

    public function sendIncomingCall(string $voipDeviceToken, array $payload, ?string $callId = null): ApnsPushResult
    {
        $payload = $this->stringify($payload);
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $attempts = 0;
        do {
            $attempts++;
            $result = $this->sendOnce($voipDeviceToken, $json, $callId);
            if (! in_array($result->statusCode, [429, 500, 503], true)) {
                return $result;
            }

            usleep(150000 * $attempts);
        } while ($attempts < 3);

        return $result;
    }

    private function sendOnce(string $voipDeviceToken, string $json, ?string $callId): ApnsPushResult
    {
        $apnsId = null;
        $ch = curl_init($this->endpoint($voipDeviceToken));
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize APNs request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'authorization: bearer '.$this->jwtFactory->token(),
                'apns-topic: '.config('apns.voip_topic'),
                'apns-push-type: voip',
                'apns-priority: 10',
                'apns-expiration: 0',
                'content-type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($response === false) {
            Log::warning('incoming_call_push.apns_failure', [
                'call_id' => $callId,
                'token' => $this->tokenFingerprint($voipDeviceToken),
                'error' => $curlError,
            ]);

            return new ApnsPushResult(0, null, $curlError ?: 'curl_error', false, false);
        }

        $headers = substr((string) $response, 0, $headerSize);
        $body = substr((string) $response, $headerSize);
        $reason = $this->reasonFromBody($body);
        if (preg_match('/^apns-id:\s*(.+)$/mi', $headers, $matches) === 1) {
            $apnsId = trim($matches[1]);
        }

        $success = $statusCode === 200;
        $shouldInvalidateToken = $statusCode === 410;

        Log::log($success ? 'info' : ($statusCode === 403 ? 'critical' : 'warning'), $success ? 'incoming_call_push.apns_success' : 'incoming_call_push.apns_failure', [
            'call_id' => $callId,
            'token' => $this->tokenFingerprint($voipDeviceToken),
            'apns_status' => $statusCode,
            'apns_reason' => $reason,
            'apns_id' => $apnsId,
        ]);

        return new ApnsPushResult($statusCode, $apnsId, $reason, $success, $shouldInvalidateToken);
    }

    private function endpoint(string $token): string
    {
        return rtrim((string) config('apns.host', 'https://api.sandbox.push.apple.com'), '/').'/3/device/'.$token;
    }

    private function stringify(array $payload): array
    {
        return collect($payload)
            ->mapWithKeys(fn (mixed $value, string|int $key): array => [(string) $key => (string) $value])
            ->all();
    }

    private function reasonFromBody(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) && isset($decoded['reason']) ? (string) $decoded['reason'] : null;
    }

    public function tokenFingerprint(string $token): string
    {
        if (strlen($token) <= 12) {
            return hash('sha256', $token);
        }

        return substr($token, 0, 6).'...'.substr($token, -6);
    }
}
