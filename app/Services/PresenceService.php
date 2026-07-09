<?php

namespace App\Services;

use App\Events\UserStatusUpdated;
use App\Models\User;
use Throwable;

class PresenceService
{
    public function markAlive(User $user, string $fallbackStatus = 'available'): User
    {
        $updates = ['last_seen_at' => now()];

        if ($user->status === 'offline') {
            $updates['status'] = $fallbackStatus;
        }

        $user->fill($updates);

        if ($user->isDirty()) {
            $previousStatus = $user->getOriginal('status');
            $user->save();

            if (($updates['status'] ?? null) !== null && $previousStatus !== $user->status) {
                $this->broadcastStatusUpdate((int) $user->id, $user->status);
            }
        }

        return $user;
    }

    public function markOffline(User $user): User
    {
        if ($user->status === 'offline') {
            return $user;
        }

        $user->update(['status' => 'offline']);
        $this->broadcastStatusUpdate((int) $user->id, 'offline');

        return $user;
    }

    public function setManualStatus(User $user, string $status): User
    {
        $user->fill([
            'status' => $status,
            'last_seen_at' => now(),
        ]);

        if (! $user->isDirty()) {
            return $user;
        }

        $previousStatus = $user->getOriginal('status');
        $user->save();

        if ($previousStatus !== $user->status) {
            $this->broadcastStatusUpdate((int) $user->id, $user->status);
        }

        return $user;
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
