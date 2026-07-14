<?php

namespace Modules\Workspace\AiTools\Write;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Workspace\AiTools\AbstractAiTool;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Services\ConversationService;

class UpdateConversationParticipantsTool extends AbstractAiTool
{
    public function __construct(
        private readonly ConversationService $conversationService,
    ) {}

    public function name(): string
    {
        return 'update_conversation_participants';
    }

    public function description(): string
    {
        return 'Add or remove participants from a group conversation. Group must keep at least 2 participants.';
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
                'conversation_id' => ['type' => 'integer'],
                'add_participants_employee_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                ],
                'remove_participants_employee_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                ],
            ],
            'required' => ['conversation_id'],
        ];
    }

    public function rules(): array
    {
        return [
            'conversation_id' => ['required', 'integer', 'exists:conversations,id'],
            'add_participants_employee_ids' => ['sometimes', 'array'],
            'add_participants_employee_ids.*' => ['integer', 'exists:users,id'],
            'remove_participants_employee_ids' => ['sometimes', 'array'],
            'remove_participants_employee_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function authorize(User $user, array $arguments): bool
    {
        $conversation = Conversation::query()->find($arguments['conversation_id'] ?? 0);

        return $conversation
            && Gate::forUser($user)->allows('manageParticipants', $conversation);
    }

    public function execute(User $user, array $arguments): array
    {
        $validated = $this->validate($arguments);
        $conversationId = $validated['conversation_id'];
        unset($validated['conversation_id']);

        $conversation = Conversation::query()->findOrFail($conversationId);
        $conversation = $this->conversationService->update($conversation, $validated, $user->id);

        return [
            'conversation_id' => $conversation->id,
            'name' => $conversation->name,
            'participant_count' => $conversation->participants->count(),
        ];
    }

    public function summarize(array $arguments): string
    {
        $parts = [];
        $addCount = count($arguments['add_participants_employee_ids'] ?? []);
        $removeCount = count($arguments['remove_participants_employee_ids'] ?? []);

        if ($addCount > 0) {
            $parts[] = "add {$addCount}";
        }
        if ($removeCount > 0) {
            $parts[] = "remove {$removeCount}";
        }

        $detail = $parts !== [] ? ' ('.implode(', ', $parts).')' : '';

        return 'Update participants in conversation #'.($arguments['conversation_id'] ?? '?').$detail;
    }
}
