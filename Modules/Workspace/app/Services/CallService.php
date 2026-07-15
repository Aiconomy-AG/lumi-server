<?php

namespace Modules\Workspace\Services;

use App\Enums\UserRole;
use App\Jobs\SendPushNotificationJob;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Workspace\Domain\Calls\CallDomainException;
use Modules\Workspace\Domain\Calls\CallMode;
use Modules\Workspace\Domain\Calls\CallStatus;
use Modules\Workspace\Domain\Calls\CallType;
use Modules\Workspace\Domain\Calls\ParticipantStatus;
use Modules\Workspace\Events\CallAccepted;
use Modules\Workspace\Events\CallCancelled;
use Modules\Workspace\Events\CallDeclined;
use Modules\Workspace\Events\CallEnded;
use Modules\Workspace\Events\CallUpdated;
use Modules\Workspace\Jobs\DispatchCallRingJob;
use Modules\Workspace\Jobs\ExpireUnansweredCallJob;
use Modules\Workspace\Jobs\SendCallPushJob;
use Modules\Workspace\Models\Call;
use Modules\Workspace\Models\CallParticipant;
use Modules\Workspace\Models\Conversation;

class CallService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly LiveKitService $liveKit,
        private readonly CallEventLogger $events,
        private readonly CallConnectionResolver $connections,
        private readonly CallChatLogService $chatLogs,
        private readonly CallPresenceService $presence,
    ) {}

    public function startWorkspaceCall(Conversation $conversation, User $caller, string $clientInstanceId, CallType $type): Call
    {
        $conversation->loadMissing('participants');

        if (! $conversation->participants->contains('id', $caller->id)) {
            throw new CallDomainException('You are not a participant in this conversation.', 'FORBIDDEN', 403);
        }

        $calleeIds = $conversation->participants
            ->where('id', '!=', $caller->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($calleeIds === []) {
            throw new CallDomainException('Conversation has no call recipients.', 'CALL_RECIPIENTS_REQUIRED', 422);
        }

        $mode = $conversation->type === 'group' || count($calleeIds) > 1
            ? CallMode::Group
            : CallMode::OneToOne;

        return $this->startCall(
            caller: $caller,
            calleeIds: $calleeIds,
            type: $type,
            mode: $mode,
            conversationId: (int) $conversation->id,
            clientInstanceId: $clientInstanceId,
        );
    }

    public function startCall(
        User $caller,
        array $calleeIds,
        CallType $type,
        CallMode $mode,
        ?int $conversationId = null,
        ?string $clientInstanceId = null,
    ): Call {
        $this->ensureEnabled();

        $calleeIds = collect($calleeIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === (int) $caller->id)
            ->values()
            ->all();

        if ($calleeIds === []) {
            throw new CallDomainException('At least one callee is required.', 'CALLEE_REQUIRED', 422);
        }

        if ($mode === CallMode::OneToOne && count($calleeIds) !== 1) {
            throw new CallDomainException('1v1 calls require exactly one callee.', 'INVALID_CALL_MODE', 422);
        }

        $this->ensureParticipantLimit($mode, count($calleeIds) + 1);

        $users = User::query()->whereIn('id', [$caller->id, ...$calleeIds])->get()->keyBy('id');
        foreach ([$caller->id, ...$calleeIds] as $userId) {
            $user = $users->get($userId);
            if (! $user instanceof User) {
                throw new CallDomainException('Call participant not found.', 'PARTICIPANT_NOT_FOUND', 404);
            }
            $this->ensureWorkspaceCallUser($user);
        }

        $participantIds = [(int) $caller->id, ...$calleeIds];

        $result = DB::transaction(function () use ($caller, $calleeIds, $type, $mode, $conversationId, $participantIds, $clientInstanceId): Call|array {
            User::query()->whereKey($participantIds)->orderBy('id')->lockForUpdate()->get();

            $busyParticipantIds = Call::query()
                ->whereIn('status', [CallStatus::Ringing->value, CallStatus::Active->value])
                ->whereHas('participants', fn ($query) => $query
                    ->whereIn('user_id', $participantIds)
                    ->whereIn('status', $this->busyParticipantStatuses()))
                ->with(['participants' => fn ($query) => $query
                    ->select(['id', 'call_id', 'user_id'])
                    ->whereIn('user_id', $participantIds)
                    ->whereIn('status', $this->busyParticipantStatuses())])
                ->get()
                ->flatMap(fn (Call $call) => $call->participants->pluck('user_id'))
                ->map(fn ($id) => (int) $id)
                ->intersect($participantIds)
                ->unique()
                ->values()
                ->all();

            if (in_array((int) $caller->id, $busyParticipantIds, true)) {
                throw new CallDomainException('You are already in a call.', 'USER_BUSY', 409);
            }

            $busyCalleeIds = array_values(array_intersect($calleeIds, $busyParticipantIds));
            if ($busyCalleeIds !== []) {
                return ['busy_callee_ids' => $busyCalleeIds];
            }

            $id = (string) Str::uuid();
            $now = now();

            $call = Call::query()->create([
                'id' => $id,
                'conversation_id' => $conversationId,
                'initiated_by_user_id' => $caller->id,
                'caller_name' => $caller->name,
                'destination_type' => Call::DESTINATION_WORKSPACE_USER,
                'mode' => $mode,
                'type' => $type,
                'media_type' => $type->value,
                'status' => CallStatus::Ringing,
                'room_name' => 'call_'.$id,
            ]);

            $participants = [
                [
                    'user_id' => $caller->id,
                    'role' => 'caller',
                    'status' => ParticipantStatus::Joined,
                    'joined_at' => $now,
                    'answered_at' => $now,
                    'client_instance_id' => $clientInstanceId,
                ],
            ];

            foreach ($calleeIds as $calleeId) {
                $participants[] = [
                    'user_id' => $calleeId,
                    'role' => 'callee',
                    'status' => ParticipantStatus::Ringing,
                    'invited_at' => $now,
                ];
            }

            $call->participants()->createMany($participants);

            return $call->load(['participants.user', 'conversation']);
        });

        if (is_array($result)) {
            $this->notifyBusyCallees($caller, $result['busy_callee_ids'], $conversationId, $type);

            throw new CallDomainException('One or more recipients are already in a call.', 'USER_BUSY', 409);
        }

        $call = $result;

        try {
            $this->liveKit->createRoom($call);
        } catch (CallDomainException $exception) {
            $call->update(['status' => CallStatus::Failed, 'end_reason' => 'livekit_error', 'ended_at' => now()]);
            $this->presence->restoreForCall($call);
            $this->recordChatLog($call->fresh(['participants.user', 'conversation']));
            throw $exception;
        }

        $this->presence->markParticipantsBusy($call, $participantIds);

        DispatchCallRingJob::dispatch($call->id);
        ExpireUnansweredCallJob::dispatch($call->id)
            ->delay(now()->addSeconds((int) config('voip.ring_timeout_seconds', 45)));

        $this->events->logCall($call, 'started', [
            'mode' => $mode->value,
            'type' => $type->value,
            'callee_ids' => $calleeIds,
        ]);

        if ($call->conversation_id === null) {
            AuditLog::recordSystem(
                module: 'workspace',
                action: 'call_start',
                entityType: 'calls',
                entityId: 0,
                label: 'Call '.$call->id,
                changes: ['new' => ['call_id' => $call->id, 'callee_ids' => $calleeIds]],
                actorName: $caller->name,
            );
        } else {
            AuditLog::record(
                module: 'workspace',
                action: 'call_start',
                entity: $call->conversation,
                label: 'Call '.$call->id,
                changes: ['new' => ['call_id' => $call->id, 'callee_ids' => $calleeIds]],
                actor: $caller,
            );
        }

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

    public function historyForUser(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return Call::query()
            ->with(['participants.user', 'conversation'])
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userId))
            ->whereIn('status', array_map(
                fn (CallStatus $status) => $status->value,
                array_filter(CallStatus::cases(), fn (CallStatus $status) => $status->isTerminal()),
            ))
            ->latest()
            ->paginate($perPage);
    }

    public function accept(string $callId, User $user, string $clientInstanceId): Call
    {
        [$call, $changed] = DB::transaction(function () use ($callId, $user, $clientInstanceId): array {
            $call = $this->lockedCall($callId, (int) $user->id);
            $participant = $this->participant($call, (int) $user->id);

            if ($call->status === CallStatus::Active) {
                if (! $call->isGroup() && $call->answered_client_instance_id !== $clientInstanceId) {
                    throw new CallDomainException('This call was answered on another device.', 'ANSWERED_ELSEWHERE', 409);
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
            $participant->update([
                'status' => ParticipantStatus::Joined,
                'answered_at' => $now,
                'joined_at' => $now,
                'client_instance_id' => $clientInstanceId,
            ]);

            $call->update([
                'status' => CallStatus::Active,
                'answered_client_instance_id' => $clientInstanceId,
                'answered_at' => $call->answered_at ?? $now,
            ]);

            return [$call->fresh(['participants.user', 'conversation']), true];
        });

        if ($changed) {
            $this->announceUpdate($call);
            $this->dispatchAccepted($call);
            $this->events->logCall($call, 'accepted', ['user_id' => $user->id]);
            $this->auditTerminalOrState($call, 'call_accept', $user);
        }

        return $call;
    }

    public function decline(string $callId, User $user): Call
    {
        [$call, $changed, $terminal] = DB::transaction(function () use ($callId, $user): array {
            $call = $this->lockedCall($callId, (int) $user->id);
            $participant = $this->participant($call, (int) $user->id);

            if ($call->status->isTerminal()) {
                return [$call, false, false];
            }

            if ($call->status !== CallStatus::Ringing) {
                throw new CallDomainException('This call is no longer ringing.', 'CALL_NOT_RINGING');
            }
            if ($participant->role !== 'callee') {
                throw new CallDomainException('Only the recipient can decline the call.', 'INVALID_CALL_ACTION', 403);
            }

            $now = now();
            $participant->update(['status' => ParticipantStatus::Declined, 'left_at' => $now]);

            $terminal = $this->resolveRingingTerminalStatus($call->fresh('participants'));

            if ($terminal !== null) {
                $call->update([
                    'status' => $terminal,
                    'ended_by_user_id' => $user->id,
                    'end_reason' => $terminal->value,
                    'ended_at' => $now,
                ]);
            }

            return [$call->fresh(['participants.user', 'conversation']), true, $terminal !== null];
        });

        if ($changed) {
            $this->announceUpdate($call);
            if ($terminal) {
                CallDeclined::dispatch($call);
            }
            $this->events->logCall($call, 'declined', ['user_id' => $user->id]);
            $this->auditTerminalOrState($call, 'call_declined', $user);
            if ($terminal) {
                $this->presence->restoreForCall($call);
                $this->recordChatLog($call);
            } else {
                $this->presence->restoreForCall($call, [(int) $user->id]);
            }
        }

        return $call;
    }

    public function cancel(string $callId, User $user): Call
    {
        return $this->finish($callId, $user, CallStatus::Cancelled, CallCancelled::class);
    }

    public function end(string $callId, User $user): Call
    {
        return $this->finish($callId, $user, CallStatus::Ended, CallEnded::class);
    }

    public function leave(string $callId, User $user): Call
    {
        [$call, $changed, $ended] = DB::transaction(function () use ($callId, $user): array {
            $call = $this->lockedCall($callId, (int) $user->id);
            $participant = $this->participant($call, (int) $user->id);

            if ($call->status->isTerminal()) {
                return [$call, false, false];
            }

            if ($call->status !== CallStatus::Active) {
                throw new CallDomainException('This call is not active.', 'CALL_NOT_ACTIVE');
            }

            $now = now();
            $participant->update(['status' => ParticipantStatus::Left, 'left_at' => $now]);

            $activeCount = $call->participants()
                ->where('status', ParticipantStatus::Joined->value)
                ->count();

            $ended = false;
            if ($activeCount === 0) {
                $call->update([
                    'status' => CallStatus::Ended,
                    'ended_by_user_id' => $user->id,
                    'end_reason' => 'left',
                    'ended_at' => $now,
                ]);
                $ended = true;
            }

            return [$call->fresh(['participants.user', 'conversation']), true, $ended];
        });

        if ($changed) {
            $this->announceUpdate($call);
            if ($ended) {
                CallEnded::dispatch($call);
            }
            $this->events->logCall($call, 'left', ['user_id' => $user->id]);
            $this->auditTerminalOrState($call, 'call_leave', $user);
            $this->presence->restoreForCall($call, [(int) $user->id]);
            if ($ended) {
                $this->liveKit->deleteRoom($call);
                $this->presence->restoreForCall($call);
                $this->recordChatLog($call);
            }
        }

        return $call;
    }

    public function invite(string $callId, User $user, array $inviteeUserIds): Call
    {
        $this->ensureEnabled();

        $inviteeUserIds = collect($inviteeUserIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($inviteeUserIds === []) {
            throw new CallDomainException('At least one invitee is required.', 'INVITEE_REQUIRED', 422);
        }

        [$call, $newInvitees, $busyInviteeIds] = DB::transaction(function () use ($callId, $user, $inviteeUserIds): array {
            $call = $this->lockedCall($callId, (int) $user->id);

            if (! $call->isGroup()) {
                throw new CallDomainException('Invites are only supported for group calls.', 'GROUP_CALL_REQUIRED', 422);
            }

            if (! in_array($call->status, [CallStatus::Ringing, CallStatus::Active], true)) {
                throw new CallDomainException('This call is no longer active.', 'CALL_NOT_ACTIVE');
            }

            $existingUserIds = $call->participants->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            $newInvitees = array_values(array_diff($inviteeUserIds, $existingUserIds));

            if ($newInvitees === []) {
                throw new CallDomainException('All users are already participants.', 'ALREADY_INVITED', 422);
            }

            $this->ensureParticipantLimit($call->mode, count($existingUserIds) + count($newInvitees));

            $users = User::query()->whereIn('id', $newInvitees)->get();
            if ($users->count() !== count($newInvitees)) {
                throw new CallDomainException('Invitee not found.', 'PARTICIPANT_NOT_FOUND', 404);
            }

            foreach ($users as $invitee) {
                $this->ensureWorkspaceCallUser($invitee);
            }

            $busyInviteeIds = Call::query()
                ->whereIn('status', [CallStatus::Ringing->value, CallStatus::Active->value])
                ->whereHas('participants', fn ($query) => $query
                    ->whereIn('user_id', $newInvitees)
                    ->whereIn('status', $this->busyParticipantStatuses()))
                ->with(['participants' => fn ($query) => $query
                    ->select(['id', 'call_id', 'user_id'])
                    ->whereIn('user_id', $newInvitees)
                    ->whereIn('status', $this->busyParticipantStatuses())])
                ->get()
                ->flatMap(fn (Call $busyCall) => $busyCall->participants->pluck('user_id'))
                ->map(fn ($id) => (int) $id)
                ->intersect($newInvitees)
                ->unique()
                ->values()
                ->all();

            if ($busyInviteeIds !== []) {
                return [$call->fresh(['participants.user', 'conversation']), [], $busyInviteeIds];
            }

            $now = now();
            foreach ($newInvitees as $inviteeId) {
                $call->participants()->create([
                    'user_id' => $inviteeId,
                    'role' => 'callee',
                    'status' => ParticipantStatus::Invited,
                    'invited_at' => $now,
                ]);
            }

            return [$call->fresh(['participants.user', 'conversation']), $newInvitees, []];
        });

        if ($newInvitees === [] && $busyInviteeIds !== []) {
            $this->notifyBusyCallees($user, $busyInviteeIds, $call->conversation_id ? (int) $call->conversation_id : null, $call->type);

            throw new CallDomainException('One or more recipients are already in a call.', 'USER_BUSY', 409);
        }

        $this->presence->markParticipantsBusy($call, $newInvitees);

        DispatchCallRingJob::dispatch($call->id, $newInvitees);
        $this->events->logCall($call, 'invited', ['invitee_ids' => $newInvitees]);
        $this->announceUpdate($call);

        return $call;
    }

    public function markMissed(string $callId): ?Call
    {
        [$call, $changed] = DB::transaction(function () use ($callId): array {
            $call = Call::query()->with('participants')->whereKey($callId)->lockForUpdate()->first();
            if (! $call || $call->status !== CallStatus::Ringing) {
                return [$call, false];
            }

            $joinedCallees = $call->participants
                ->where('role', 'callee')
                ->where('status', ParticipantStatus::Joined)
                ->count();

            if ($joinedCallees > 0) {
                return [$call, false];
            }

            $now = now();
            $call->update(['status' => CallStatus::Missed, 'end_reason' => 'missed', 'ended_at' => $now]);
            $call->participants()
                ->where('role', 'callee')
                ->whereIn('status', [ParticipantStatus::Ringing->value, ParticipantStatus::Invited->value])
                ->update(['status' => ParticipantStatus::Missed->value, 'left_at' => $now]);

            return [$call->fresh(['participants.user', 'conversation']), true];
        });

        if ($changed && $call) {
            $calleeIds = $call->participants
                ->where('role', 'callee')
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $this->notifications->createForRecipients(
                type: 'call_missed',
                source: 'call',
                recipientUserIds: $calleeIds,
                actorUserId: (int) $call->initiated_by_user_id,
                conversationId: $call->conversation_id ? (int) $call->conversation_id : null,
                payload: [
                    'call_id' => $call->id,
                    'caller_name' => $call->caller_name,
                    'caller_user_id' => (string) $call->initiated_by_user_id,
                    'destination_type' => $call->destination_type,
                ],
            );
            $this->announceUpdate($call);
            $this->events->logCall($call, 'missed');
            $this->presence->restoreForCall($call);
            AuditLog::recordSystem(
                module: 'workspace',
                action: 'call_missed',
                entityType: $call->conversation_id ? 'conversations' : 'calls',
                entityId: (int) ($call->conversation_id ?? 0),
                label: 'Call '.$call->id,
                changes: ['new' => ['call_id' => $call->id, 'status' => 'missed']],
            );
            $this->recordChatLog($call);
        }

        return $call;
    }

    private function finish(
        string $callId,
        User $user,
        CallStatus $status,
        ?string $granularEvent = null,
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
            if ($status === CallStatus::Ended) {
                if ($call->isGroup()) {
                    throw new CallDomainException('Group call participants must leave individually.', 'GROUP_CALL_LEAVE_REQUIRED', 422);
                }

                if ($call->status !== CallStatus::Active) {
                    throw new CallDomainException('This call is not active.', 'CALL_NOT_ACTIVE');
                }
            } elseif ($call->status !== CallStatus::Ringing) {
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

            if ($status === CallStatus::Ended) {
                $call->participants()
                    ->where('status', ParticipantStatus::Joined->value)
                    ->update(['status' => ParticipantStatus::Left->value, 'left_at' => $now]);
            }

            return [$call->fresh(['participants.user', 'conversation']), true];
        });

        if ($changed) {
            $this->announceUpdate($call);
            if ($granularEvent !== null) {
                $granularEvent::dispatch($call);
            }
            $this->events->logCall($call, $status->value, ['user_id' => $user->id]);
            $this->auditTerminalOrState($call, 'call_'.$status->value, $user);
            $this->presence->restoreForCall($call);
            if ($status === CallStatus::Ended) {
                $this->liveKit->deleteRoom($call);
            }
            $this->recordChatLog($call);
        }

        return $call;
    }

    private function recordChatLog(Call $call): void
    {
        if ($this->chatLogs->shouldRecord($call)) {
            $this->chatLogs->recordTerminalCall($call);
        }
    }

    private function resolveRingingTerminalStatus(Call $call): ?CallStatus
    {
        $callees = $call->participants->where('role', 'callee');
        $joinedCallees = $callees->where('status', ParticipantStatus::Joined)->count();

        if ($joinedCallees > 0) {
            return null;
        }

        $pendingCallees = $callees->filter(fn (CallParticipant $p) => $p->isPending())->count();
        if ($pendingCallees > 0) {
            return null;
        }

        $declinedCallees = $callees->where('status', ParticipantStatus::Declined)->count();

        return $declinedCallees === $callees->count()
            ? CallStatus::Declined
            : CallStatus::Missed;
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
        $call->loadMissing(['participants.user']);

        foreach ($call->participants as $participant) {
            $connection = $this->connections->connectionForParticipant($call, $participant);
            CallUpdated::dispatch($call, (int) $participant->user_id, $connection);

            SendCallPushJob::dispatch(
                (int) $participant->user_id,
                'Lumi call updated',
                'Call status: '.$call->status->value,
                $this->pushPayload($call, 'workspace_call_updated'),
            );
        }
    }

    private function dispatchAccepted(Call $call): void
    {
        $call->loadMissing(['participants.user']);

        foreach ($call->participants as $participant) {
            $connection = $this->connections->connectionForParticipant($call, $participant);
            CallAccepted::dispatch($call, (int) $participant->user_id, $connection);
        }
    }

    private function notifyBusyCallees(User $caller, array $calleeIds, ?int $conversationId, CallType $type): void
    {
        $attemptId = (string) Str::uuid();
        $payload = [
            'attempt_id' => $attemptId,
            'caller_user_id' => (string) $caller->id,
            'caller_name' => $caller->name,
            'conversation_id' => (string) ($conversationId ?? ''),
            'media_type' => $type->value,
        ];

        $this->notifications->createForRecipients(
            type: 'call_attempted_while_busy',
            source: 'call',
            recipientUserIds: $calleeIds,
            actorUserId: (int) $caller->id,
            conversationId: $conversationId,
            payload: $payload,
        );

        foreach ($calleeIds as $calleeId) {
            SendPushNotificationJob::dispatch(
                (int) $calleeId,
                'Missed call attempt',
                $caller->name.' tried to call while you were busy',
                ['type' => 'call_attempted_while_busy', ...$payload],
            );
        }
    }

    private function busyParticipantStatuses(): array
    {
        return [
            ParticipantStatus::Invited->value,
            ParticipantStatus::Joined->value,
            ParticipantStatus::Ringing->value,
        ];
    }

    private function pushPayload(Call $call, string $type): array
    {
        return [
            'type' => $type,
            'call_id' => $call->id,
            'room_name' => $call->room_name,
            'conversation_id' => (string) ($call->conversation_id ?? ''),
            'status' => $call->status->value,
            'destination_type' => $call->destination_type,
            'caller_user_id' => (string) $call->initiated_by_user_id,
            'caller_name' => $call->caller_name,
            'call_type' => $call->callTypeValue(),
            'call_mode' => $call->callModeValue(),
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

    private function ensureParticipantLimit(CallMode $mode, int $participantCount): void
    {
        if ($mode !== CallMode::Group) {
            return;
        }

        $maxParticipants = (int) config('voip.livekit.max_participants_group', 10);
        if ($maxParticipants > 0 && $participantCount > $maxParticipants) {
            throw new CallDomainException(
                'Group calls support up to '.$maxParticipants.' participants.',
                'CALL_PARTICIPANT_LIMIT_EXCEEDED',
                422,
            );
        }
    }

    private function auditTerminalOrState(Call $call, string $action, User $actor): void
    {
        if ($call->conversation_id === null) {
            AuditLog::recordSystem(
                module: 'workspace',
                action: $action,
                entityType: 'calls',
                entityId: 0,
                label: 'Call '.$call->id,
                changes: ['new' => ['call_id' => $call->id, 'status' => $call->status->value]],
                actorName: $actor->name,
            );

            return;
        }

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
