<?php

namespace Modules\Workspace\Services;

use Agence104\LiveKit\WebhookReceiver;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livekit\WebhookEvent;
use Modules\Workspace\Domain\Calls\CallStatus;
use Modules\Workspace\Domain\Calls\LiveKitWebhookException;
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
        private readonly CallChatLogService $chatLogs,
        private readonly CallPresenceService $presence,
    ) {}

    public function handle(string $body, ?string $authorizationHeader): void
    {
        if (json_decode($body) === null && json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('LiveKit webhook rejected malformed JSON.', [
                'json_error' => json_last_error_msg(),
            ]);

            throw LiveKitWebhookException::malformed();
        }

        $this->validateAuthorizationHeader($body, $authorizationHeader);

        try {
            $event = (new WebhookReceiver(
                config('voip.livekit.api_key'),
                config('voip.livekit.api_secret'),
            ))->receive($body, $authorizationHeader, true);
        } catch (\JsonException $exception) {
            Log::warning('LiveKit webhook rejected malformed payload.', [
                'error' => $exception->getMessage(),
            ]);

            throw LiveKitWebhookException::malformed();
        } catch (\Throwable $exception) {
            Log::warning('LiveKit webhook rejected malformed payload.', [
                'error' => $exception->getMessage(),
            ]);

            throw LiveKitWebhookException::malformed();
        }

        $call = $this->findCall($event);
        if (! $call) {
            Log::info('LiveKit webhook ignored for unknown room.', [
                'event' => $event->getEvent(),
                'event_id' => $event->getId(),
                'room' => $event->getRoom()?->getName(),
            ]);

            return;
        }

        $this->events->log(
            $call->id,
            'webhook_'.$event->getEvent(),
            ['event_id' => $event->getId()],
        );

        match ($event->getEvent()) {
            'participant_joined' => $this->participantJoined($event, $call),
            'participant_left' => $this->participantLeft($event, $call),
            'room_finished' => $this->roomFinished($call),
            default => null,
        };
    }

    private function participantJoined(WebhookEvent $event, ?Call $call = null): void
    {
        $call ??= $this->findCall($event);
        $participantInfo = $event->getParticipant();
        if (! $call || $participantInfo === null) {
            return;
        }

        $identity = $participantInfo->getIdentity();
        $participant = $this->matchParticipant($call, $identity);
        if (! $participant) {
            return;
        }

        $changed = DB::transaction(function () use ($call, $participant, $identity): bool {
            $lockedCall = Call::query()->whereKey($call->id)->lockForUpdate()->first();
            if (! $lockedCall || $lockedCall->status->isTerminal()) {
                return false;
            }

            $lockedParticipant = CallParticipant::query()
                ->whereKey($participant->id)
                ->lockForUpdate()
                ->first();
            if (! $lockedParticipant) {
                return false;
            }

            $alreadyProcessed = $lockedParticipant->status === ParticipantStatus::Joined
                && $lockedParticipant->livekit_identity === $identity
                && $lockedCall->started_at !== null;
            if ($alreadyProcessed) {
                return false;
            }

            $now = now();
            $lockedParticipant->update([
                'status' => ParticipantStatus::Joined,
                'joined_at' => $lockedParticipant->joined_at ?? $now,
                'livekit_identity' => $identity,
            ]);

            if ($lockedCall->started_at === null) {
                $lockedCall->update(['started_at' => $now]);
            }

            if ($lockedCall->status === CallStatus::Ringing && $lockedParticipant->role === 'callee') {
                $lockedCall->update(['status' => CallStatus::Active, 'answered_at' => $now]);
            }

            return true;
        });

        if (! $changed) {
            return;
        }

        $call->refresh()->load(['participants.user', 'conversation']);
        ParticipantJoined::dispatch($call, $participant->fresh());
        $this->events->logCall($call, 'joined', ['identity' => $identity]);
    }

    private function participantLeft(WebhookEvent $event, ?Call $call = null): void
    {
        $call ??= $this->findCall($event);
        $participantInfo = $event->getParticipant();
        if (! $call || $participantInfo === null) {
            return;
        }

        $identity = $participantInfo->getIdentity();
        $participant = $this->matchParticipant($call, $identity);
        if (! $participant) {
            return;
        }

        [$changed, $ended] = DB::transaction(function () use ($call, $participant): array {
            $lockedCall = Call::query()->whereKey($call->id)->lockForUpdate()->first();
            $lockedParticipant = CallParticipant::query()
                ->whereKey($participant->id)
                ->lockForUpdate()
                ->first();
            if (! $lockedCall || ! $lockedParticipant || $lockedParticipant->status === ParticipantStatus::Left) {
                return [false, false];
            }

            $now = now();
            $lockedParticipant->update([
                'status' => ParticipantStatus::Left,
                'left_at' => $now,
            ]);

            $activeCount = $lockedCall->participants()
                ->where('status', ParticipantStatus::Joined->value)
                ->count();

            $ended = false;
            if ($activeCount === 0 && $lockedCall->status === CallStatus::Active) {
                $lockedCall->participants()
                    ->whereIn('status', [ParticipantStatus::Ringing->value, ParticipantStatus::Invited->value])
                    ->update(['status' => ParticipantStatus::Missed->value, 'left_at' => $now]);
                $lockedCall->update([
                    'status' => CallStatus::Ended,
                    'ended_at' => $now,
                    'end_reason' => 'ended',
                ]);
                $ended = true;
            }

            return [true, $ended];
        });

        if (! $changed) {
            return;
        }

        $call->refresh()->load(['participants.user', 'conversation']);
        ParticipantLeft::dispatch($call, $participant->fresh());
        $this->presence->restoreForCall($call, [(int) $participant->user_id]);

        if ($ended) {
            $this->presence->restoreForCall($call);
            CallEnded::dispatch($call);
            $this->chatLogs->recordTerminalCall($call);
        }

        $this->events->logCall($call, 'left', ['identity' => $identity]);
    }

    private function roomFinished(Call|WebhookEvent $callOrEvent): void
    {
        $call = $callOrEvent instanceof WebhookEvent
            ? $this->findCall($callOrEvent)
            : $callOrEvent;

        if (! $call) {
            return;
        }

        [$call, $ended] = DB::transaction(function () use ($call): array {
            $lockedCall = Call::query()
                ->with('participants')
                ->whereKey($call->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedCall) {
                return [$call, false];
            }

            $now = now();
            $lockedCall->participants()
                ->where('status', ParticipantStatus::Joined->value)
                ->update([
                    'status' => ParticipantStatus::Left->value,
                    'left_at' => $now,
                ]);
            $lockedCall->participants()
                ->whereIn('status', [ParticipantStatus::Ringing->value, ParticipantStatus::Invited->value])
                ->update([
                    'status' => ParticipantStatus::Missed->value,
                    'left_at' => $now,
                ]);

            $ended = false;
            if (! $lockedCall->status->isTerminal()) {
                $lockedCall->update([
                    'status' => CallStatus::Ended,
                    'ended_at' => $now,
                    'end_reason' => 'ended',
                ]);
                $ended = true;
            }

            return [$lockedCall->fresh(['participants.user', 'conversation']), $ended];
        });

        if ($ended) {
            CallEnded::dispatch($call);
        }
        $this->presence->restoreForCall($call);
        $this->events->logCall($call, 'ended', ['source' => 'room_finished']);
        if ($call->status->isTerminal()) {
            $this->chatLogs->recordTerminalCall($call);
        }
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

    private function validateAuthorizationHeader(string $body, ?string $authorizationHeader): void
    {
        if (! $authorizationHeader) {
            Log::warning('LiveKit webhook rejected missing authorization header.');

            throw LiveKitWebhookException::unauthorized();
        }

        try {
            $claims = JWT::decode(
                $authorizationHeader,
                new Key((string) config('voip.livekit.api_secret'), 'HS256'),
            );
        } catch (\Throwable $exception) {
            Log::warning('LiveKit webhook rejected invalid authorization token.', [
                'error' => $exception->getMessage(),
            ]);

            throw LiveKitWebhookException::unauthorized();
        }

        $expectedHash = base64_encode(hash('sha256', $body, true));
        if (($claims->iss ?? null) !== config('voip.livekit.api_key') || ($claims->sha256 ?? null) !== $expectedHash) {
            Log::warning('LiveKit webhook rejected authorization claims.', [
                'issuer_matches' => ($claims->iss ?? null) === config('voip.livekit.api_key'),
                'sha256_matches' => ($claims->sha256 ?? null) === $expectedHash,
            ]);

            throw LiveKitWebhookException::unauthorized();
        }
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
