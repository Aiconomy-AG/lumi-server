<?php

namespace Modules\Workspace\Services;

use App\Events\UserStatusUpdated;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Workspace\Domain\Calls\CallStatus;
use Modules\Workspace\Models\Call;
use Throwable;

class CallPresenceService
{
    public function markParticipantsBusy(Call $call, array $userIds): void
    {
        $userIds = collect($userIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($userIds === []) {
            return;
        }

        User::query()
            ->whereIn('id', $userIds)
            ->whereIn('status', ['available', 'busy'])
            ->orderBy('id')
            ->get()
            ->each(function (User $user) use ($call): void {
                $originalStatus = (string) $user->status;

                $user->forceFill([
                    'status' => 'busy',
                    'call_status_restore_status' => $user->call_status_restore_status ?: $originalStatus,
                    'call_status_restore_call_id' => $call->id,
                ])->save();

                if ($originalStatus !== 'busy') {
                    $this->broadcastStatusUpdate((int) $user->id, 'busy');
                }
            });
    }

    public function restoreForCall(Call $call, array $userIds = []): void
    {
        $query = User::query()
            ->where('call_status_restore_call_id', $call->id)
            ->orderBy('id');

        if ($userIds !== []) {
            $query->whereIn('id', collect($userIds)->map(fn ($id) => (int) $id)->unique()->values()->all());
        }

        $query->get()->each(function (User $user) use ($call): void {
            if ($this->hasOtherRingingOrActiveCall((int) $user->id, (string) $call->id)) {
                return;
            }

            $restoreStatus = in_array($user->call_status_restore_status, ['available', 'busy'], true)
                ? $user->call_status_restore_status
                : 'busy';
            $previousStatus = (string) $user->status;

            $user->forceFill([
                'status' => $restoreStatus,
                'call_status_restore_status' => null,
                'call_status_restore_call_id' => null,
            ])->save();

            if ($previousStatus !== $restoreStatus) {
                $this->broadcastStatusUpdate((int) $user->id, $restoreStatus);
            }
        });
    }

    public function updateManualStatus(User $user, string $status): User
    {
        if ($user->call_status_restore_call_id !== null && in_array($status, ['available', 'busy'], true)) {
            $user->forceFill([
                'status' => 'busy',
                'call_status_restore_status' => $status,
                'last_seen_at' => now(),
            ])->save();

            return $user;
        }

        if ($user->call_status_restore_call_id !== null && $status === 'away') {
            $user->forceFill([
                'status' => 'away',
                'call_status_restore_status' => null,
                'call_status_restore_call_id' => null,
                'last_seen_at' => now(),
            ])->save();

            return $user;
        }

        $user->forceFill([
            'status' => $status,
            'last_seen_at' => now(),
        ])->save();

        return $user;
    }

    private function hasOtherRingingOrActiveCall(int $userId, string $currentCallId): bool
    {
        return DB::table('call_participants')
            ->join('calls', 'calls.id', '=', 'call_participants.call_id')
            ->where('call_participants.user_id', $userId)
            ->where('calls.id', '!=', $currentCallId)
            ->whereIn('calls.status', [CallStatus::Ringing->value, CallStatus::Active->value])
            ->whereIn('call_participants.status', ['invited', 'joined', 'ringing'])
            ->exists();
    }

    private function broadcastStatusUpdate(int $userId, string $status): void
    {
        try {
            event(new UserStatusUpdated($userId, $status));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
