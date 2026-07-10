<?php

namespace Modules\Workspace\Services;

use App\Events\NotificationDismissed;
use App\Events\NotificationDelivered;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Modules\Workspace\Models\NotificationDelivery;
use Modules\Workspace\Models\NotificationEvent;

class NotificationService
{
    public function getForUser(int $userId, bool $unreadOnly = false): Collection
    {
        return NotificationDelivery::query()
            ->where('recipient_user_id', $userId)
            ->whereNull('dismissed_at')
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->with(['event.actor'])
            ->latest()
            ->get();
    }

    public function createForRecipients(
        string $type,
        string $source,
        array $recipientUserIds,
        ?int $actorUserId = null,
        ?int $taskId = null,
        ?int $conversationId = null,
        ?int $messageId = null,
        array $payload = [],
    ): ?NotificationEvent {
        $recipientUserIds = collect($recipientUserIds)
            ->filter()
            ->unique()
            ->values();

        if ($recipientUserIds->isEmpty()) {
            return null;
        }

        $event = NotificationEvent::query()->create([
            'actor_user_id' => $actorUserId,
            'type' => $type,
            'source' => $source,
            'task_id' => $taskId,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'payload' => $payload ?: null,
        ]);

        $recipientUserIds->each(function (int $recipientUserId) use ($event): void {
            $delivery = NotificationDelivery::query()->create([
                'notification_event_id' => $event->id,
                'recipient_user_id' => $recipientUserId,
            ]);

            event(new NotificationDelivered($delivery));
        });

        return $event->load('deliveries');
    }

    public function markAsRead(NotificationDelivery $delivery): NotificationDelivery
    {
        if ($delivery->read_at === null) {
            $delivery->update(['read_at' => Carbon::now()]);
        }

        return $delivery->refresh()->load(['event.actor']);
    }

    public function dismiss(NotificationDelivery $delivery): NotificationDelivery
    {
        if ($delivery->dismissed_at === null) {
            $delivery->update([
                'dismissed_at' => Carbon::now(),
                'read_at' => $delivery->read_at ?? Carbon::now(),
            ]);
        }

        $delivery = $delivery->refresh()->load(['event.actor']);

        event(new NotificationDismissed($delivery));

        return $delivery;
    }

    public function markAllAsRead(int $userId): int
    {
        return NotificationDelivery::query()
            ->where('recipient_user_id', $userId)
            ->whereNull('dismissed_at')
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }
}
