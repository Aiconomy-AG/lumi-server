<?php

namespace Modules\Workspace\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Workspace\Models\Project;

class ProjectPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function view(User $user, Project $project): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function update(User $user, Project $project): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isAdmin() || $user->isEmployee();
    }
}
