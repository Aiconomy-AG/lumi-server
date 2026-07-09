<?php

namespace Modules\Workspace\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Workspace\Models\Conversation;


class ConversationService
{

    public function getAllForUser(int $userId): Collection
    {
        return Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('users.id',$userId))
            ->with('participants')
            ->get();
    }

    public function getById(int $conversationId): ?Conversation
    {
        return Conversation::query()
            ->with('participants')
            ->find($conversationId);
    }

    public function create(array $data, int $creatorId): Conversation
    {
        $participantIds = $data['participants_employee_ids'];
        if ($data['type'] === 'direct'){
            $existing = $this->findExistingDirectConversation($creatorId,$participantIds[0]);

            if ($existing){
                return $existing;
            }
        }

        $conversation = Conversation::create([
            'type'=>$data['type'],
            'name'=>$data['name'] ?? null,
            'created_by'=>$creatorId,
        ]);

        $allParticipantIds = array_unique([...$participantIds, $creatorId]);
        $conversation->participants()->attach($allParticipantIds);

        return $conversation->load('participants');
    }


    private function findExistingDirectConversation(int $userId, int $otherId): ?Conversation{
        return Conversation::query()
            ->where('type', 'direct')
            ->whereHas('participants', fn ($q) => $q->where('users.id', $userId))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $otherId))
            ->with('participants')
            ->get()
            ->first(fn ($c) => $c->participants->count() === 2);
    }
}
