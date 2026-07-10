<?php

namespace Modules\Workspace\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Workspace\Models\Conversation;

class ConversationService
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function getAllForUser(int $userId): Collection
    {
        return Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('users.id', $userId))
            ->with(['participants', 'latestMessage'])
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->get();
    }

    public function getById(int $conversationId): ?Conversation
    {
        return Conversation::query()
            ->with(['participants', 'latestMessage'])
            ->find($conversationId);
    }

    public function create(array $data, int $creatorId): Conversation
    {
        $participantIds = $data['participants_employee_ids'];
        if ($data['type'] === 'direct') {
            $existing = $this->findExistingDirectConversation($creatorId, $participantIds[0]);

            if ($existing) {
                return $existing;
            }
        }

        $conversation = Conversation::create([
            'type' => $data['type'],
            'name' => $data['name'] ?? null,
            'created_by' => $creatorId,
        ]);

        $allParticipantIds = array_unique([...$participantIds, $creatorId]);
        $conversation->participants()->attach($allParticipantIds);

        $conversation = $conversation->load(['participants', 'latestMessage']);

        $this->notificationService->createForRecipients(
            type: 'chat_added_to_conversation',
            source: 'chat',
            recipientUserIds: array_values(array_diff($allParticipantIds, [$creatorId])),
            actorUserId: $creatorId,
            conversationId: $conversation->id,
            payload: [
                'conversation_name' => $conversation->name,
                'conversation_type' => $conversation->type,
            ],
        );

        return $conversation;
    }

    public function update(Conversation $conversation, array $data, int $actorUserId): Conversation
    {
        if ($conversation->type !== 'group') {
            throw new \InvalidArgumentException('Only group conversations can be updated.');
        }

        if (array_key_exists('name', $data)) {
            $trimmedName = trim((string) ($data['name'] ?? ''));
            if ($trimmedName !== '') {
                $conversation->update(['name' => $trimmedName]);
            }
        }

        $newParticipantIds = $data['add_participants_employee_ids'] ?? [];
        if ($newParticipantIds !== []) {
            $existingIds = $conversation->participants()->pluck('users.id')->all();
            $toAttach = array_values(array_diff($newParticipantIds, $existingIds));

            if ($toAttach !== []) {
                $conversation->participants()->attach($toAttach);

                $this->notificationService->createForRecipients(
                    type: 'chat_added_to_conversation',
                    source: 'chat',
                    recipientUserIds: $toAttach,
                    actorUserId: $actorUserId,
                    conversationId: $conversation->id,
                    payload: [
                        'conversation_name' => $conversation->name,
                        'conversation_type' => $conversation->type,
                    ],
                );
            }
        }

        $removeParticipantIds = $data['remove_participants_employee_ids'] ?? [];
        if ($removeParticipantIds !== []) {
            $existingIds = $conversation->participants()->pluck('users.id')->all();
            $toDetach = array_values(array_intersect($removeParticipantIds, $existingIds));

            if ($toDetach !== []) {
                $remainingCount = count($existingIds) - count($toDetach);
                if ($remainingCount < 2) {
                    throw new \InvalidArgumentException('A group must keep at least two participants.');
                }

                $conversation->participants()->detach($toDetach);
            }
        }

        return $conversation->fresh(['participants', 'latestMessage']);
    }

    private function findExistingDirectConversation(int $userId, int $otherId): ?Conversation
    {
        return Conversation::query()
            ->where('type', 'direct')
            ->whereHas('participants', fn ($q) => $q->where('users.id', $userId))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $otherId))
            ->with(['participants', 'latestMessage'])
            ->get()
            ->first(fn ($c) => $c->participants->count() === 2);
    }
}
