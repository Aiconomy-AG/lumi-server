<?php

namespace Modules\Workspace\AiTools\Read;

use App\Enums\UserRole;
use App\Models\User;
use Modules\Workspace\AiTools\AbstractAiTool;

class ListUsersTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'list_users';
    }

    public function description(): string
    {
        return 'List staff users (employees and admins) for name-to-ID resolution. Optional search by name or email.';
    }

    public function isWrite(): bool
    {
        return false;
    }

    protected function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => ['type' => 'string', 'description' => 'Search by name or email'],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function authorize(User $user, array $arguments): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $validated = $this->validate($arguments);

        $query = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Employee, UserRole::Admin])
            ->orderBy('name');

        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->limit(30)->get(['id', 'name', 'email', 'role']);

        return [
            'users' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role?->value,
            ])->all(),
        ];
    }

    public function summarize(array $arguments): string
    {
        return isset($arguments['search'])
            ? 'Search users: '.$arguments['search']
            : 'List users';
    }
}
