<?php

namespace Modules\Workspace\Infrastructure;

use App\Models\User;
use Firebase\JWT\JWT;
use Modules\Workspace\Contracts\MediaRoomTokenProvider;
use Modules\Workspace\Domain\Calls\CallDomainException;
use Modules\Workspace\Models\Call;

class LiveKitMediaRoomTokenProvider implements MediaRoomTokenProvider
{
    public function connectionFor(Call $call, User $user, string $clientInstanceId): array
    {
        $url = (string) config('voip.livekit.url');
        $apiKey = (string) config('voip.livekit.api_key');
        $apiSecret = (string) config('voip.livekit.api_secret');

        if ($url === '' || $apiKey === '' || strlen($apiSecret) < 32) {
            throw new CallDomainException('Calling is not configured.', 'VOIP_NOT_CONFIGURED', 503);
        }

        $now = now()->timestamp;
        $payload = [
            'iss' => $apiKey,
            'sub' => sprintf('user:%d:client:%s', $user->id, $clientInstanceId),
            'nbf' => $now - 5,
            'exp' => $now + (int) config('voip.livekit.token_ttl_seconds', 900),
            'name' => $user->name,
            'metadata' => json_encode([
                'user_id' => $user->id,
                'call_id' => $call->id,
                'client_instance_id' => $clientInstanceId,
            ], JSON_THROW_ON_ERROR),
            'video' => [
                'roomJoin' => true,
                'room' => $call->room_name,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => false,
                'canPublishSources' => ['microphone'],
            ],
        ];

        return ['url' => $url, 'token' => JWT::encode($payload, $apiSecret, 'HS256')];
    }
}
