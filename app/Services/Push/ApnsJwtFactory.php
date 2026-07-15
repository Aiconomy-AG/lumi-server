<?php

namespace App\Services\Push;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ApnsJwtFactory
{
    private ?string $cachedToken = null;

    private int $cachedAt = 0;

    public function token(): string
    {
        $ttlSeconds = max((int) config('apns.jwt_ttl_seconds', 3000), 60);
        if ($this->cachedToken !== null && time() - $this->cachedAt < $ttlSeconds - 60) {
            return $this->cachedToken;
        }

        $path = (string) config('apns.p8_path');
        $keyId = (string) config('apns.key_id');
        $teamId = (string) config('apns.team_id');
        $bundleId = (string) config('apns.bundle_id');

        if ($path === '' || ! is_readable($path)) {
            throw new RuntimeException('APNs private key is not readable.');
        }

        foreach (['key_id' => $keyId, 'team_id' => $teamId, 'bundle_id' => $bundleId] as $name => $value) {
            if ($value === '') {
                throw new RuntimeException("APNs {$name} is not configured.");
            }
        }

        if (! (bool) config('apns.use_sandbox', true)) {
            Log::warning('APNs sandbox is disabled, but this project expects sandbox-only VoIP pushes.');
        }

        $privateKey = file_get_contents($path);
        if (! is_string($privateKey) || $privateKey === '') {
            throw new RuntimeException('APNs private key is empty.');
        }

        $this->cachedAt = time();
        $this->cachedToken = JWT::encode([
            'iss' => $teamId,
            'iat' => $this->cachedAt,
        ], $privateKey, 'ES256', $keyId, ['typ' => 'JWT']);

        return $this->cachedToken;
    }
}
