<?php

namespace Modules\Workspace\Services;

use Agence104\LiveKit\RoomCreateOptions;
use Agence104\LiveKit\RoomServiceClient;
use Modules\Workspace\Domain\Calls\CallDomainException;
use Modules\Workspace\Models\Call;

class LiveKitService
{
    public function createRoom(Call $call): void
    {
        $url = $this->httpUrl();
        $apiKey = (string) config('voip.livekit.api_key');
        $apiSecret = (string) config('voip.livekit.api_secret');

        if ($url === '' || $apiKey === '' || strlen($apiSecret) < 32) {
            throw new CallDomainException('Calling is not configured.', 'VOIP_NOT_CONFIGURED', 503);
        }

        $maxParticipants = $call->isGroup()
            ? (int) config('voip.livekit.max_participants_group', 10)
            : (int) config('voip.livekit.max_participants_1v1', 2);

        $options = (new RoomCreateOptions())
            ->setName($call->room_name)
            ->setEmptyTimeout((int) config('voip.livekit.empty_timeout_seconds', 60))
            ->setMaxParticipants($maxParticipants);

        (new RoomServiceClient($url, $apiKey, $apiSecret))->createRoom($options);
    }

    public function deleteRoom(Call $call): void
    {
        $url = $this->httpUrl();
        $apiKey = (string) config('voip.livekit.api_key');
        $apiSecret = (string) config('voip.livekit.api_secret');

        if ($url === '' || $apiKey === '' || strlen($apiSecret) < 32 || $call->room_name === null) {
            return;
        }

        try {
            (new RoomServiceClient($url, $apiKey, $apiSecret))->deleteRoom($call->room_name);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function httpUrl(): string
    {
        $url = (string) config('voip.livekit.url');

        return str_replace('wss://', 'https://', str_replace('ws://', 'http://', $url));
    }
}
