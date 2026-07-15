<?php

namespace Modules\Workspace\AiTools\Read;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Workspace\AiTools\AbstractAiTool;

class GetCurrentTimeTool extends AbstractAiTool
{
    public function name(): string
    {
        return 'get_current_time';
    }

    public function description(): string
    {
        return 'Get the current date and time in the workspace timezone (Europe/Bucharest). '
            .'Use this when the user asks what time or day it is, or when scheduling relative to "today".';
    }

    public function isWrite(): bool
    {
        return false;
    }

    protected function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
        ];
    }

    public function rules(): array
    {
        return [];
    }

    public function authorize(User $user, array $arguments): bool
    {
        return true;
    }

    public function execute(User $user, array $arguments): array
    {
        $timezone = (string) config('chat_ai.workspace_timezone', 'Europe/Bucharest');
        $now = Carbon::now($timezone);

        return [
            'timezone' => $timezone,
            'iso8601' => $now->toIso8601String(),
            'date' => $now->toDateString(),
            'time' => $now->format('H:i'),
            'day_of_week' => $now->format('l'),
            'hour' => $now->hour,
            'is_weekend' => $now->isWeekend(),
        ];
    }

    public function summarize(array $arguments): string
    {
        return 'Get current workspace time';
    }
}
