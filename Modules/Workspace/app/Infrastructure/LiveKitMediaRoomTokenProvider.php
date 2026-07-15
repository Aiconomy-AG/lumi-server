<?php

namespace Modules\Workspace\Infrastructure;

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;
use App\Models\User;
use Modules\Workspace\Contracts\MediaRoomTokenProvider;
use Modules\Workspace\Domain\Calls\CallDomainException;
use Modules\Workspace\Domain\Calls\CallType;
use Modules\Workspace\Models\Call;
use Modules\Workspace\Support\LiveKitIdentity;

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

        $identity = LiveKitIdentity::forUser((int) $user->id, $clientInstanceId);
        $callType = $call->type ?? CallType::tryFrom((string) $call->media_type) ?? CallType::Audio;

        $videoGrant = (new VideoGrant)
            ->setRoomJoin()
            ->setRoomName($call->room_name)
            ->setCanPublish(true)
            ->setCanSubscribe(true)
            ->setCanPublishData(false);

        $videoGrant->setCanPublishSources(
            $callType === CallType::Audio
                ? ['microphone']
                : ['microphone', 'camera', 'screen_share'],
        );

        $tokenOptions = (new AccessTokenOptions)
            ->setIdentity($identity)
            ->setName($user->name)
            ->setMetadata(json_encode([
                'user_id' => $user->id,
                'call_id' => $call->id,
                'client_instance_id' => $clientInstanceId,
            ], JSON_THROW_ON_ERROR))
            ->setTtl((int) config('voip.livekit.token_ttl_seconds', 900));

        $token = (new AccessToken($apiKey, $apiSecret))
            ->init($tokenOptions)
            ->setGrant($videoGrant)
            ->toJwt();

        return ['url' => $url, 'token' => $token];
    }
}
