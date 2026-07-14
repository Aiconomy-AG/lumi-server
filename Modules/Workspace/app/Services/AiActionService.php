<?php

namespace Modules\Workspace\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Workspace\AiTools\ToolRegistry;
use Modules\Workspace\Enums\AiActionStatus;
use Modules\Workspace\Events\AiActionUpdated;
use Modules\Workspace\Events\MessageSent;
use Modules\Workspace\Models\AiAction;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Message;
use Modules\Workspace\Services\AiChat\ProposedAction;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AiActionService
{
    public function __construct(
        private readonly ChatAiUserResolver $botResolver,
        private readonly ToolRegistry $toolRegistry,
    ) {}

    public function expirePendingActionsInConversation(int $conversationId): void
    {
        $pending = AiAction::query()
            ->where('conversation_id', $conversationId)
            ->where('status', AiActionStatus::Pending)
            ->with('message')
            ->get();

        foreach ($pending as $action) {
            $action->update(['status' => AiActionStatus::Expired]);

            if ($action->message) {
                $this->patchMessageStatus($action->message, AiActionStatus::Expired);
            }
        }
    }

    public function createPending(
        Conversation $conversation,
        User $requestedBy,
        ProposedAction $proposed,
    ): AiAction {
        $ttlMinutes = (int) config('chat_ai.action_ttl_minutes', 15);
        $expiresAt = now()->addMinutes($ttlMinutes);

        $bot = $this->botResolver->botUser();
        if ($bot === null) {
            throw new \RuntimeException('AI bot user not found.');
        }

        return DB::transaction(function () use ($conversation, $requestedBy, $proposed, $expiresAt, $bot) {
            $this->expirePendingActionsInConversation($conversation->id);

            $action = AiAction::query()->create([
                'conversation_id' => $conversation->id,
                'requested_by_user_id' => $requestedBy->id,
                'tool_name' => $proposed->toolName,
                'arguments' => $proposed->arguments,
                'summary' => $proposed->summary,
                'status' => AiActionStatus::Pending,
                'expires_at' => $expiresAt,
            ]);

            $fallbackText = "I'd like to: {$proposed->summary}. Please confirm or reject this action.";

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $bot->id,
                'message' => $fallbackText,
                'type' => 'ai_action',
                'meta' => [
                    'action_id' => $action->id,
                    'tool_name' => $proposed->toolName,
                    'summary' => $proposed->summary,
                    'arguments' => $proposed->arguments,
                    'status' => AiActionStatus::Pending->value,
                    'requested_by_user_id' => $requestedBy->id,
                    'requested_by_name' => $requestedBy->name,
                    'expires_at' => $expiresAt->toISOString(),
                ],
            ]);

            $action->update(['message_id' => $message->id]);

            return $action->fresh(['message']);
        });
    }

    public function approve(AiAction $action, User $user, int $conversationId): AiAction
    {
        $this->assertConversationMatch($action, $conversationId);
        $this->assertRequester($action, $user);
        $this->assertParticipant($action, $user);

        $expired = false;

        $claimed = DB::transaction(function () use ($action, $user, &$expired) {
            $locked = AiAction::query()->whereKey($action->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw new NotFoundHttpException('Action not found.');
            }

            if ($locked->status === AiActionStatus::Executed) {
                return $locked;
            }

            if ($locked->status !== AiActionStatus::Pending) {
                throw new ConflictHttpException('Action is no longer pending.');
            }

            if ($locked->isExpired()) {
                $this->markActionExpired($locked);
                $expired = true;

                return null;
            }

            $tool = $this->toolRegistry->get($locked->tool_name);
            if ($tool === null || ! $this->toolRegistry->isAllowedFor($user, $locked->tool_name)) {
                throw new AccessDeniedHttpException('Tool not allowed.');
            }

            if (! $tool->authorize($user, $locked->arguments)) {
                throw new AccessDeniedHttpException('Not authorized to execute this action.');
            }

            $validated = $tool->validate($locked->arguments);

            $locked->update([
                'status' => AiActionStatus::Approved,
                'arguments' => $validated,
            ]);

            return $locked->fresh(['message']);
        });

        if ($expired) {
            throw new GoneHttpException('Action has expired.');
        }

        if ($claimed->status === AiActionStatus::Executed) {
            return $claimed;
        }

        return $this->executeApproved($claimed, $user);
    }

    public function reject(AiAction $action, User $user, int $conversationId): AiAction
    {
        $this->assertConversationMatch($action, $conversationId);
        $this->assertRequester($action, $user);
        $this->assertParticipant($action, $user);

        $expired = false;

        $rejected = DB::transaction(function () use ($action, &$expired) {
            $locked = AiAction::query()->whereKey($action->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== AiActionStatus::Pending) {
                throw new ConflictHttpException('Action is no longer pending.');
            }

            if ($locked->isExpired()) {
                $this->markActionExpired($locked);
                $expired = true;

                return null;
            }

            $locked->update(['status' => AiActionStatus::Rejected]);

            if ($locked->message) {
                $this->patchMessageStatus($locked->message, AiActionStatus::Rejected);
            }

            $this->postOutcomeMessage(
                $locked,
                'Action rejected.',
            );

            return $locked->fresh(['message']);
        });

        if ($expired) {
            throw new GoneHttpException('Action has expired.');
        }

        return $rejected;
    }

    private function markActionExpired(AiAction $action): void
    {
        $action->update(['status' => AiActionStatus::Expired]);

        if ($action->message) {
            $this->patchMessageStatus($action->message, AiActionStatus::Expired);
        }
    }

    private function executeApproved(AiAction $action, User $user): AiAction
    {
        $tool = $this->toolRegistry->get($action->tool_name);

        try {
            $result = $tool->execute($user, $action->arguments);

            $action->update([
                'status' => AiActionStatus::Executed,
                'executed_at' => now(),
                'result' => $result,
            ]);

            AuditLog::record(
                module: $this->auditModuleFor($action->tool_name),
                action: $this->auditActionFor($action->tool_name),
                entity: $action,
                label: $action->summary,
                changes: ['new' => $result],
                description: "AI-proposed action approved in conversation #{$action->conversation_id}",
                actor: $user,
            );

            if ($action->message) {
                $meta = $action->message->meta ?? [];
                $meta['status'] = AiActionStatus::Executed->value;
                $meta['result'] = $result;
                $action->message->update(['meta' => $meta]);
                AiActionUpdated::dispatch($action->message->fresh());
            }

            $this->postOutcomeMessage($action, $this->outcomeText($action, $result));

            return $action->fresh(['message']);
        } catch (\Throwable $e) {
            $action->update([
                'status' => AiActionStatus::Failed,
                'executed_at' => now(),
                'error' => $e->getMessage(),
            ]);

            if ($action->message) {
                $meta = $action->message->meta ?? [];
                $meta['status'] = AiActionStatus::Failed->value;
                $meta['error'] = $e->getMessage();
                $action->message->update(['meta' => $meta]);
                AiActionUpdated::dispatch($action->message->fresh());
            }

            $this->postOutcomeMessage($action, 'Action failed: '.$e->getMessage());

            return $action->fresh(['message']);
        }
    }

    private function postOutcomeMessage(AiAction $action, string $text): void
    {
        $bot = $this->botResolver->botUser();
        if ($bot === null) {
            return;
        }

        $message = Message::query()->create([
            'conversation_id' => $action->conversation_id,
            'sender_id' => $bot->id,
            'message' => $text,
            'type' => 'text',
        ]);

        MessageSent::dispatch($message);
    }

    private function patchMessageStatus(Message $message, AiActionStatus $status): void
    {
        $meta = $message->meta ?? [];
        $meta['status'] = $status->value;
        $message->update(['meta' => $meta]);
        AiActionUpdated::dispatch($message->fresh());
    }

    private function assertConversationMatch(AiAction $action, int $conversationId): void
    {
        if ($action->conversation_id !== $conversationId) {
            throw new NotFoundHttpException('Action not found.');
        }
    }

    private function assertRequester(AiAction $action, User $user): void
    {
        if ($action->requested_by_user_id !== $user->id) {
            throw new AccessDeniedHttpException('Only the requester can approve or reject this action.');
        }
    }

    private function assertParticipant(AiAction $action, User $user): void
    {
        $isParticipant = Conversation::query()
            ->whereKey($action->conversation_id)
            ->whereHas('participants', fn ($q) => $q->whereKey($user->id))
            ->exists();

        if (! $isParticipant) {
            throw new AccessDeniedHttpException('You are not a participant in this conversation.');
        }
    }

    private function auditModuleFor(string $toolName): string
    {
        return str_starts_with($toolName, 'update_stock') || str_starts_with($toolName, 'search_products')
            ? 'sales'
            : 'workspace';
    }

    private function auditActionFor(string $toolName): string
    {
        return match ($toolName) {
            'create_task' => 'ai_task_create',
            'update_task' => 'ai_task_update',
            'delete_task' => 'ai_task_delete',
            'assign_task_employees' => 'ai_task_assign',
            'update_stock' => 'ai_stock_update',
            'create_group_conversation' => 'ai_conversation_create',
            'update_conversation_participants' => 'ai_conversation_update',
            default => 'ai_'.$toolName,
        };
    }

    /** @param  array<string, mixed>  $result */
    private function outcomeText(AiAction $action, array $result): string
    {
        return match ($action->tool_name) {
            'create_task' => 'Done — created task #'.($result['task_id'] ?? '?').': '.($result['title'] ?? ''),
            'update_task' => 'Done — updated task #'.($result['task_id'] ?? '?'),
            'delete_task' => 'Done — deleted task #'.($result['task_id'] ?? '?'),
            'assign_task_employees' => 'Done — assigned employees to task #'.($result['task_id'] ?? '?'),
            'update_stock' => 'Done — set stock of SKU '.($result['sku'] ?? '?')
                .' from '.($result['old_stock_quantity'] ?? '?')
                .' to '.($result['new_stock_quantity'] ?? '?'),
            'create_group_conversation' => 'Done — created group chat "'.($result['name'] ?? '').'"',
            'update_conversation_participants' => 'Done — updated participants in conversation #'.($result['conversation_id'] ?? '?'),
            default => 'Done — '.$action->summary,
        };
    }
}
