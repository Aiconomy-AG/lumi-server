<?php

namespace Modules\Workspace\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Workspace\Domain\Messages\MessageType;
use Modules\Workspace\Events\ConversationDeleted;
use Modules\Workspace\Events\MessageSent;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Message;

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
            ->orderByRaw('COALESCE(messages_max_created_at, conversations.created_at) DESC')
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

        if ($data['type'] === 'group') {
            $creator = User::query()->find($creatorId);
            $creatorName = $creator?->name ?? 'Someone';
            $groupName = trim((string) ($conversation->name ?? ''));

            $this->postSystemMessage(
                $conversation,
                $creatorId,
                $groupName !== ''
                    ? "{$creatorName} created the group \"{$groupName}\"."
                    : "{$creatorName} created the group.",
            );
        }

        $conversation = $conversation->fresh(['participants', 'latestMessage']);

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

                $this->postParticipantChangeMessage($conversation, $actorUserId, $toAttach, added: true);
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

                $this->postParticipantChangeMessage($conversation, $actorUserId, $toDetach, added: false);
                $conversation->participants()->detach($toDetach);
            }
        }

        return $conversation->fresh(['participants', 'latestMessage']);
    }

    public function leave(Conversation $conversation, int $userId): void
    {
        if ($conversation->type !== 'group') {
            throw new \InvalidArgumentException('Only group conversations can be left.');
        }

        $name = User::query()->whereKey($userId)->value('name') ?? 'Someone';

        $this->postSystemMessage($conversation, $userId, "{$name} left the group.");

        $conversation->participants()->detach($userId);
    }

    public function delete(Conversation $conversation): void
    {
        if ($conversation->type !== 'group') {
            throw new \InvalidArgumentException('Only group conversations can be deleted.');
        }

        $conversation->delete();

        ConversationDeleted::dispatch($conversation->id);
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

    private function postSystemMessage(Conversation $conversation, int $senderId, string $text): Message
    {
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $senderId,
            'message_type' => MessageType::System,
            'message' => $text,
            'type' => 'text',
        ]);

        try {
            MessageSent::dispatch($message);
        } catch (\Throwable $e) {
            Log::warning('System message broadcast failed', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $message;
    }

    /** @param  array<int, int>  $userIds */
    private function postParticipantChangeMessage(
        Conversation $conversation,
        int $actorUserId,
        array $userIds,
        bool $added,
    ): void {
        if ($userIds === []) {
            return;
        }

        $actorName = User::query()->whereKey($actorUserId)->value('name') ?? 'Someone';
        $names = User::query()->whereIn('id', $userIds)->pluck('name')->all();
        $targetNames = $names !== [] ? implode(', ', $names) : implode(', ', $userIds);
        $verb = $added ? 'added' : 'removed';
        $preposition = $added ? 'to' : 'from';

        $this->postSystemMessage(
            $conversation,
            $actorUserId,
            "{$actorName} {$verb} {$targetNames} {$preposition} the group.",
        );
    }
}
