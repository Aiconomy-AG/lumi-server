<?php

namespace Modules\Workspace\Services;

use Agence104\LiveKit\WebhookReceiver;
use Illuminate\Support\Facades\DB;
use Livekit\WebhookEvent;
use Modules\Workspace\Domain\Calls\CallStatus;
use Modules\Workspace\Domain\Calls\ParticipantStatus;
use Modules\Workspace\Events\CallEnded;
use Modules\Workspace\Events\ParticipantJoined;
use Modules\Workspace\Events\ParticipantLeft;
use Modules\Workspace\Models\Call;
use Modules\Workspace\Models\CallParticipant;

class CallWebhookService
{
    public function __construct(
        private readonly CallEventLogger $events,
    ) {}

    public function handle(string $body, ?string $authorizationHeader): void
    {
        $event = (new WebhookReceiver(
            config('voip.livekit.api_key'),
            config('voip.livekit.api_secret'),
        ))->receive($body, $authorizationHeader);

        $this->events->log(
            $this->resolveCallId($event) ?? 'unknown',
            'webhook_'.$event->getEvent(),
            ['event_id' => $event->getId()],
        );

        match ($event->getEvent()) {
            'participant_joined' => $this->participantJoined($event),
            'participant_left' => $this->participantLeft($event),
            'room_finished' => $this->roomFinished($event),
            default => null,
        };
    }

    private function participantJoined(WebhookEvent $event): void
    {
        $call = $this->findCall($event);
        $participantInfo = $event->getParticipant();
        if (! $call || $participantInfo === null) {
            return;
        }

        $identity = $participantInfo->getIdentity();
        $participant = $this->matchParticipant($call, $identity);
        if (! $participant) {
            return;
        }

        DB::transaction(function () use ($call, $participant, $identity): void {
            $lockedCall = Call::query()->whereKey($call->id)->lockForUpdate()->first();
            if (! $lockedCall) {
                return;
            }

            $now = now();
            $participant->update([
                'status' => ParticipantStatus::Joined,
                'joined_at' => $participant->joined_at ?? $now,
                'livekit_identity' => $identity,
            ]);

            if ($lockedCall->started_at === null) {
                $lockedCall->update(['started_at' => $now]);
            }

            if ($lockedCall->status === CallStatus::Ringing && $participant->role === 'callee') {
                $lockedCall->update(['status' => CallStatus::Active, 'answered_at' => $now]);
            }
        });

        $call->refresh()->load(['participants.user', 'conversation']);
        ParticipantJoined::dispatch($call, $participant->fresh());
        $this->events->logCall($call, 'joined', ['identity' => $identity]);
    }

    private function participantLeft(WebhookEvent $event): void
    {
        $call = $this->findCall($event);
        $participantInfo = $event->getParticipant();
        if (! $call || $participantInfo === null) {
            return;
        }

        $identity = $participantInfo->getIdentity();
        $participant = $this->matchParticipant($call, $identity);
        if (! $participant) {
            return;
        }

        DB::transaction(function () use ($call, $participant): void {
            $participant->update([
                'status' => ParticipantStatus::Left,
                'left_at' => now(),
            ]);

            $activeCount = $call->participants()
                ->where('status', ParticipantStatus::Joined->value)
                ->count();

            if ($activeCount === 0 && $call->status === CallStatus::Active) {
                $call->update([
                    'status' => CallStatus::Ended,
                    'ended_at' => now(),
                    'end_reason' => 'ended',
                ]);
            }
        });

        $call->refresh()->load(['participants.user', 'conversation']);
        ParticipantLeft::dispatch($call, $participant->fresh());

        if ($call->status === CallStatus::Ended) {
            CallEnded::dispatch($call);
        }

        $this->events->logCall($call, 'left', ['identity' => $identity]);
    }

    private function roomFinished(WebhookEvent $event): void
    {
        $call = $this->findCall($event);
        if (! $call || $call->status->isTerminal()) {
            return;
        }

        $call->update([
            'status' => CallStatus::Ended,
            'ended_at' => now(),
            'end_reason' => 'ended',
        ]);

        $call->refresh()->load(['participants.user', 'conversation']);
        CallEnded::dispatch($call);
        $this->events->logCall($call, 'ended', ['source' => 'room_finished']);
    }

    private function findCall(WebhookEvent $event): ?Call
    {
        $room = $event->getRoom();
        if ($room === null) {
            return null;
        }

        return Call::query()
            ->with(['participants.user', 'conversation'])
            ->where('room_name', $room->getName())
            ->first();
    }

    private function resolveCallId(WebhookEvent $event): ?string
    {
        return $this->findCall($event)?->id;
    }

    private function matchParticipant(Call $call, string $identity): ?CallParticipant
    {
        $participant = $call->participants->firstWhere('livekit_identity', $identity);
        if ($participant) {
            return $participant;
        }

        if (preg_match('/^user:(\d+):client:/', $identity, $matches)) {
            return $call->participants->firstWhere('user_id', (int) $matches[1]);
        }

        return null;
    }
}
