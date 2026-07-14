<?php

namespace Modules\Workspace\Services;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Workspace\Domain\Calls\CallDomainException;
use Modules\Workspace\Domain\Calls\CallStatus;
use Modules\Workspace\Domain\Calls\ParticipantStatus;
use Modules\Workspace\Events\CallRinging;
use Modules\Workspace\Events\CallUpdated;
use Modules\Workspace\Jobs\ExpireUnansweredCallJob;
use Modules\Workspace\Jobs\SendCallPushJob;
use Modules\Workspace\Models\Call;
use Modules\Workspace\Models\CallParticipant;
use Modules\Workspace\Models\Conversation;

class CallService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function startWorkspaceCall(Conversation $conversation, User $caller): Call
    {
        $this->ensureEnabled();
        $conversation->loadMissing('participants');

        if ($conversation->type !== 'direct' || $conversation->participants->count() !== 2) {
            throw new CallDomainException('Only direct conversations support calls.', 'DIRECT_CALL_REQUIRED', 422);
        }

        if (! $conversation->participants->contains('id', $caller->id)) {
            throw new CallDomainException('You are not a participant in this conversation.', 'FORBIDDEN', 403);
        }

        $recipient = $conversation->participants->firstWhere('id', '!=', $caller->id);
        if (! $recipient instanceof User) {
            throw new CallDomainException('Direct conversation recipient was not found.', 'DIRECT_CALL_REQUIRED', 422);
        }

        foreach ([$caller, $recipient] as $participant) {
            $this->ensureWorkspaceCallUser($participant);
        }

        $participantIds = [(int) $caller->id, (int) $recipient->id];

        $call = DB::transaction(function () use ($conversation, $caller, $recipient, $participantIds): Call {
            User::query()->whereKey($participantIds)->orderBy('id')->lockForUpdate()->get();

            $busy = Call::query()
                ->whereIn('status', [CallStatus::Ringing->value, CallStatus::Active->value])
                ->whereHas('participants', fn ($query) => $query->whereIn('user_id', $participantIds))
                ->exists();

            if ($busy) {
                throw new CallDomainException('One of the participants is already in a call.', 'USER_BUSY');
            }

            $id = (string) Str::uuid();
            $call = Call::query()->create([
                'id' => $id,
                'conversation_id' => $conversation->id,
                'initiated_by_user_id' => $caller->id,
                'caller_name' => $caller->name,
                'destination_type' => Call::DESTINATION_WORKSPACE_USER,
                'media_type' => 'audio',
                'status' => CallStatus::Ringing,
                'room_name' => 'lumi-call-'.$id,
            ]);

            $call->participants()->createMany([
                [
                    'user_id' => $caller->id,
                    'role' => 'caller',
                    'status' => ParticipantStatus::Joined,
                    'answered_at' => now(),
                ],
                [
                    'user_id' => $recipient->id,
                    'role' => 'callee',
                    'status' => ParticipantStatus::Ringing,
                ],
            ]);

            return $call->load(['participants.user', 'conversation']);
        });

        CallRinging::dispatch($call, (int) $recipient->id);
        SendCallPushJob::dispatch(
            (int) $recipient->id,
            'Incoming Lumi call',
            $caller->name.' is calling',
            $this->pushPayload($call, 'workspace_call_incoming'),
        );
        ExpireUnansweredCallJob::dispatch($call->id)
            ->delay(now()->addSeconds((int) config('voip.ring_timeout_seconds', 45)));

        AuditLog::record(
            module: 'workspace',
            action: 'call_start',
            entity: $conversation,
            label: 'Call '.$call->id,
            changes: ['new' => ['call_id' => $call->id, 'recipient_user_id' => $recipient->id]],
        );

        return $call;
    }

    public function getForParticipant(string $callId, int $userId): Call
    {
        $call = Call::query()
            ->with(['participants.user', 'conversation'])
            ->whereKey($callId)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userId))
            ->first();

        if (! $call) {
            throw new CallDomainException('Call not found.', 'CALL_NOT_FOUND', 404);
        }

        return $call;
    }

    public function activeForUser(int $userId): ?Call
    {
        return Call::query()
            ->with(['participants.user', 'conversation'])
            ->whereIn('status', [CallStatus::Ringing->value, CallStatus::Active->value])
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userId))
            ->latest()
            ->first();
    }

    public function accept(string $callId, User $user, string $clientInstanceId): Call
    {
        [$call, $changed] = DB::transaction(function () use ($callId, $user, $clientInstanceId): array {
            $call = $this->lockedCall($callId, (int) $user->id);
            $participant = $this->participant($call, (int) $user->id);

            if ($call->status === CallStatus::Active) {
                if ($call->answered_client_instance_id !== $clientInstanceId) {
                    throw new CallDomainException('This call was answered on another device.', 'ANSWERED_ELSEWHERE');
                }

                return [$call, false];
            }

            if ($call->status !== CallStatus::Ringing) {
                throw new CallDomainException('This call is no longer ringing.', 'CALL_NOT_RINGING');
            }
            if ($participant->role !== 'callee') {
                throw new CallDomainException('Only the recipient can accept the call.', 'INVALID_CALL_ACTION', 403);
            }

            $now = now();
            $participant->update(['status' => ParticipantStatus::Joined, 'answered_at' => $now]);
            $call->update([
                'status' => CallStatus::Active,
                'answered_client_instance_id' => $clientInstanceId,
                'answered_at' => $now,
            ]);

            return [$call->fresh(['participants.user', 'conversation']), true];
        });

        if ($changed) {
            $this->announceUpdate($call);
            $this->auditTerminalOrState($call, 'call_accept', $user);
        }

        return $call;
    }

    public function decline(string $callId, User $user): Call
    {
        return $this->finish($callId, $user, CallStatus::Declined);
    }

    public function cancel(string $callId, User $user): Call
    {
        return $this->finish($callId, $user, CallStatus::Cancelled);
    }

    public function end(string $callId, User $user): Call
    {
        return $this->finish($callId, $user, CallStatus::Ended);
    }

    public function markMissed(string $callId): ?Call
    {
        [$call, $changed] = DB::transaction(function () use ($callId): array {
            $call = Call::query()->whereKey($callId)->lockForUpdate()->first();
            if (! $call || $call->status !== CallStatus::Ringing) {
                return [$call, false];
            }

            $call->update(['status' => CallStatus::Missed, 'end_reason' => 'missed', 'ended_at' => now()]);
            $call->participants()->where('role', 'callee')->update([
                'status' => ParticipantStatus::Missed->value,
                'left_at' => now(),
            ]);

            return [$call->fresh(['participants.user', 'conversation']), true];
        });

        if ($changed && $call) {
            $calleeId = (int) $call->participants->firstWhere('role', 'callee')->user_id;
            $this->notifications->createForRecipients(
                type: 'call_missed',
                source: 'call',
                recipientUserIds: [$calleeId],
                actorUserId: (int) $call->initiated_by_user_id,
                conversationId: (int) $call->conversation_id,
                payload: [
                    'call_id' => $call->id,
                    'caller_name' => $call->caller_name,
                    'caller_user_id' => (string) $call->initiated_by_user_id,
                    'destination_type' => $call->destination_type,
                ],
            );
            $this->announceUpdate($call);
            AuditLog::recordSystem(
                module: 'workspace',
                action: 'call_missed',
                entityType: 'conversations',
                entityId: (int) $call->conversation_id,
                label: 'Call '.$call->id,
                changes: ['new' => ['call_id' => $call->id, 'status' => 'missed']],
            );
        }

        return $call;
    }

    private function finish(
        string $callId,
        User $user,
        CallStatus $status,
    ): Call {
        [$call, $changed] = DB::transaction(function () use ($callId, $user, $status): array {
            $call = $this->lockedCall($callId, (int) $user->id);
            $participant = $this->participant($call, (int) $user->id);

            if ($call->status->isTerminal()) {
                return [$call, false];
            }

            $requiredRole = match ($status) {
                CallStatus::Declined => 'callee',
                CallStatus::Cancelled => 'caller',
                default => null,
            };
            if ($requiredRole !== null && $participant->role !== $requiredRole) {
                $message = $requiredRole === 'callee'
                    ? 'Only the recipient can decline the call.'
                    : 'Only the caller can cancel the call.';
                throw new CallDomainException($message, 'INVALID_CALL_ACTION', 403);
            }
            if ($status !== CallStatus::Ended && $call->status !== CallStatus::Ringing) {
                throw new CallDomainException('This call is no longer ringing.', 'CALL_NOT_RINGING');
            }

            $now = now();
            $participantStatus = $status === CallStatus::Declined
                ? ParticipantStatus::Declined
                : ParticipantStatus::Left;
            $participant->update(['status' => $participantStatus, 'left_at' => $now]);
            $call->update([
                'status' => $status,
                'ended_by_user_id' => $user->id,
                'end_reason' => $status->value,
                'ended_at' => $now,
            ]);

            return [$call->fresh(['participants.user', 'conversation']), true];
        });

        if ($changed) {
            $this->announceUpdate($call);
            $this->auditTerminalOrState($call, 'call_'.$status->value, $user);
        }

        return $call;
    }

    private function lockedCall(string $callId, int $userId): Call
    {
        $call = Call::query()->whereKey($callId)->lockForUpdate()->first();
        if (! $call || ! $call->participants()->where('user_id', $userId)->exists()) {
            throw new CallDomainException('Call not found.', 'CALL_NOT_FOUND', 404);
        }

        return $call->load('participants');
    }

    private function participant(Call $call, int $userId): CallParticipant
    {
        $participant = $call->participants->firstWhere('user_id', $userId);
        if (! $participant) {
            throw new CallDomainException('You are not a call participant.', 'FORBIDDEN', 403);
        }

        return $participant;
    }

    private function announceUpdate(Call $call): void
    {
        CallUpdated::dispatch($call);
        foreach ($call->participants as $participant) {
            SendCallPushJob::dispatch(
                (int) $participant->user_id,
                'Lumi call updated',
                'Call status: '.$call->status->value,
                $this->pushPayload($call, 'workspace_call_updated'),
            );
        }
    }

    private function pushPayload(Call $call, string $type): array
    {
        return [
            'type' => $type,
            'call_id' => $call->id,
            'conversation_id' => (string) $call->conversation_id,
            'status' => $call->status->value,
            'destination_type' => $call->destination_type,
            'caller_user_id' => (string) $call->initiated_by_user_id,
            'caller_name' => $call->caller_name,
        ];
    }

    private function ensureWorkspaceCallUser(User $user): void
    {
        $isStaff = in_array($user->role, [UserRole::Employee, UserRole::Admin], true);
        $isBot = strcasecmp((string) $user->email, (string) config('chat_ai.user_email')) === 0;

        if (! $user->is_active || ! $isStaff || $isBot) {
            throw new CallDomainException('Workspace calls are only available between active staff users.', 'WORKSPACE_CALL_PARTICIPANT_REQUIRED', 403);
        }
    }

    private function auditTerminalOrState(Call $call, string $action, User $actor): void
    {
        AuditLog::record(
            module: 'workspace',
            action: $action,
            entity: $call->conversation,
            label: 'Call '.$call->id,
            changes: ['new' => ['call_id' => $call->id, 'status' => $call->status->value]],
            actor: $actor,
        );
    }

    private function ensureEnabled(): void
    {
        if (! config('voip.enabled')) {
            throw new CallDomainException('Calling is disabled.', 'VOIP_DISABLED', 503);
        }
    }
}
