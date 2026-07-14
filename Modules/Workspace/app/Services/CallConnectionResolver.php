<?php

namespace Modules\Workspace\Services;

use App\Models\User;
use Modules\Workspace\Contracts\MediaRoomTokenProvider;
use Modules\Workspace\Domain\Calls\CallStatus;
use Modules\Workspace\Domain\Calls\ParticipantStatus;
use Modules\Workspace\Models\Call;
use Modules\Workspace\Models\CallParticipant;

class CallConnectionResolver
{
    public function __construct(
        private readonly MediaRoomTokenProvider $mediaTokens,
    ) {}

    public function connectionForParticipant(Call $call, CallParticipant $participant): ?array
    {
        if (! $this->shouldIncludeConnection($call, $participant)) {
            return null;
        }

        $participant->loadMissing('user');
        if (! $participant->user instanceof User) {
            return null;
        }

        return $this->mediaTokens->connectionFor(
            $call,
            $participant->user,
            (string) $participant->client_instance_id,
        );
    }

    public function connectionForRequestUser(Call $call, User $user, string $clientInstanceId): ?array
    {
        if (! in_array($call->status, [CallStatus::Ringing, CallStatus::Active], true)) {
            return null;
        }

        $participant = $call->participants->firstWhere('user_id', $user->id);
        if (! $participant instanceof CallParticipant) {
            return null;
        }

        if (! $this->canJoinWithClientInstance($call, $participant, $clientInstanceId)) {
            return null;
        }

        return $this->mediaTokens->connectionFor($call, $user, $clientInstanceId);
    }

    public function canJoinWithClientInstance(
        Call $call,
        CallParticipant $participant,
        string $clientInstanceId,
    ): bool {
        if (! in_array($call->status, [CallStatus::Ringing, CallStatus::Active], true)) {
            return false;
        }

        return $participant->role === 'caller'
            || ($call->status === CallStatus::Active
                && ($call->isGroup() || $call->answered_client_instance_id === $clientInstanceId));
    }

    private function shouldIncludeConnection(Call $call, CallParticipant $participant): bool
    {
        if ($participant->client_instance_id === null || $participant->client_instance_id === '') {
            return false;
        }

        if ($participant->role === 'caller') {
            return in_array($call->status, [CallStatus::Ringing, CallStatus::Active], true);
        }

        return $call->status === CallStatus::Active
            && $participant->status === ParticipantStatus::Joined;
    }
}
