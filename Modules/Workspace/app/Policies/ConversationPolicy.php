<?php

namespace Modules\Workspace\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Workspace\Models\Conversation;

class ConversationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    public function manageParticipants(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation)
            && $conversation->type === 'group';
    }

    public function leave(User $user, Conversation $conversation): bool
    {
        return $conversation->type === 'group'
            && $this->isParticipant($user, $conversation);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        if ($conversation->type !== 'group') {
            return false;
        }

        return $user->isAdmin()
            || (int) $conversation->created_by === (int) $user->id;
    }

    private function isParticipant(User $user, Conversation $conversation): bool
    {
        if ($conversation->relationLoaded('participants')) {
            return $conversation->participants->contains('id', $user->id);
        }

        return $conversation->participants()->whereKey($user->id)->exists();
    }
}
