<?php

namespace Modules\Workspace\AiTools\Write;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Workspace\AiTools\AbstractAiTool;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Services\ConversationService;

class CreateGroupConversationTool extends AbstractAiTool
{
    public function __construct(
        private readonly ConversationService $conversationService,
    ) {}

    public function name(): string
    {
        return 'create_group_conversation';
    }

    public function description(): string
    {
        return 'Create a new group chat with a name and participant user IDs.';
    }

    public function isWrite(): bool
    {
        return true;
    }

    protected function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Group chat name'],
                'participants_employee_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'User IDs to add (creator is added automatically)',
                ],
            ],
            'required' => ['name', 'participants_employee_ids'],
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'participants_employee_ids' => ['required', 'array', 'min:1'],
            'participants_employee_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function authorize(User $user, array $arguments): bool
    {
        return Gate::forUser($user)->allows('create', Conversation::class);
    }

    public function execute(User $user, array $arguments): array
    {
        $validated = $this->validate($arguments);

        $conversation = $this->conversationService->create([
            'type' => 'group',
            'name' => $validated['name'],
            'participants_employee_ids' => $validated['participants_employee_ids'],
        ], $user->id);

        return [
            'conversation_id' => $conversation->id,
            'name' => $conversation->name,
            'participant_count' => $conversation->participants->count(),
        ];
    }

    public function summarize(array $arguments): string
    {
        $name = $arguments['name'] ?? 'Unnamed group';
        $count = count($arguments['participants_employee_ids'] ?? []);

        return "Create group chat \"{$name}\" with {$count} participant(s)";
    }
}
