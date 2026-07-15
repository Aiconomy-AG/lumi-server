<?php

namespace Modules\Workspace\AiTools;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Container\Container;

class ToolRegistry
{
    /** @var array<string, ToolContract>|null */
    private ?array $tools = null;

    public function __construct(
        private readonly Container $container,
    ) {}

    public function get(string $name): ?ToolContract
    {
        return $this->all()[$name] ?? null;
    }

    /** @return array<string, ToolContract> */
    public function all(): array
    {
        if ($this->tools !== null) {
            return $this->tools;
        }

        $this->tools = [];

        foreach (config('chat_ai.tools', []) as $name => $class) {
            $this->tools[$name] = $this->container->make($class);
        }

        return $this->tools;
    }

    /** @return array<int, array{name: string, description: string, parameters: array}> */
    public function declarationsFor(User $user): array
    {
        $allowed = $this->allowedToolNamesFor($user);

        return collect($this->all())
            ->filter(fn (ToolContract $tool, string $name) => in_array($name, $allowed, true))
            ->map(fn (ToolContract $tool) => $tool->declaration())
            ->values()
            ->all();
    }

    public function isAllowedFor(User $user, string $toolName): bool
    {
        return in_array($toolName, $this->allowedToolNamesFor($user), true);
    }

    /** @return array<int, string> */
    private function allowedToolNamesFor(User $user): array
    {
        $role = $user->role?->value ?? UserRole::Client->value;
        $allowed = config("chat_ai.tool_roles.{$role}", []);

        if (! config('chat_ai.image_enabled', false)) {
            $allowed = array_values(array_diff($allowed, ['generate_image']));
        }

        return $allowed;
    }
}
