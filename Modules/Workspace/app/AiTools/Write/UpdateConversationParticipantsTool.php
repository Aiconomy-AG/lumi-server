<?php

namespace Modules\Workspace\AiTools\Write;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        return 'Add or remove members from a group chat. Requires conversation_id. '
            .'Use list_users first to resolve names to user ids. '
            .'When the user says "add X to this group", use the current conversation id from context. '
            .'The group must keep at least 2 participants after removals.';
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
                'conversation_id' => ['type' => 'integer', 'description' => 'Target group conversation id'],
                'add_participants_employee_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'User ids to add to the group',
                ],
                'remove_participants_employee_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'User ids to remove from the group',
                ],
            ],
            'required' => ['conversation_id'],
        ];
    }

    public function rules(): array
    {
        return [
            'conversation_id' => [
                'required',
                'integer',
                Rule::exists('conversations', 'id')->where('type', 'group'),
            ],
            'add_participants_employee_ids' => ['sometimes', 'array'],
            'add_participants_employee_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'remove_participants_employee_ids' => ['sometimes', 'array'],
            'remove_participants_employee_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    /** @param  array<string, mixed>  $arguments */
    public function validate(array $arguments): array
    {
        $validated = parent::validate($arguments);

        $add = $validated['add_participants_employee_ids'] ?? [];
        $remove = $validated['remove_participants_employee_ids'] ?? [];

        if ($add === [] && $remove === []) {
            throw ValidationException::withMessages([
                'add_participants_employee_ids' => ['Provide at least one user id to add or remove.'],
            ]);
        }

        return $validated;
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
        $addIds = $arguments['add_participants_employee_ids'] ?? [];
        $removeIds = $arguments['remove_participants_employee_ids'] ?? [];

        if ($addIds !== []) {
            $names = User::query()->whereIn('id', $addIds)->pluck('name')->all();
            $parts[] = 'Add '.($names !== [] ? implode(', ', $names) : implode(', ', $addIds));
        }

        if ($removeIds !== []) {
            $names = User::query()->whereIn('id', $removeIds)->pluck('name')->all();
            $parts[] = 'Remove '.($names !== [] ? implode(', ', $names) : implode(', ', $removeIds));
        }

        $groupLabel = '';
        if (isset($arguments['conversation_id'])) {
            $name = Conversation::query()->whereKey($arguments['conversation_id'])->value('name');
            $groupLabel = $name ? " in \"{$name}\"" : ' in group #'.$arguments['conversation_id'];
        }

        return ($parts !== [] ? implode('; ', $parts) : 'Update group members').$groupLabel;
    }
}
