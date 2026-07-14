<?php

namespace Modules\Workspace\AiTools;

use App\Models\User;

interface ToolContract
{
    public function name(): string;

    public function description(): string;

    /** @return array{name: string, description: string, parameters: array} */
    public function declaration(): array;

    public function isWrite(): bool;

    /** @return array<string, mixed> */
    public function rules(): array;

    public function authorize(User $user, array $arguments): bool;

  /** @return array<string, mixed> */
    public function execute(User $user, array $arguments): array;

    public function summarize(array $arguments): string;
}
