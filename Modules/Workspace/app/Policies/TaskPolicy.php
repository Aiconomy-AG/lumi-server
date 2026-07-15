<?php

namespace Modules\Workspace\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Workspace\Models\Task;

class TaskPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function view(User $user, Task $task): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function update(User $user, Task $task): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }
}
