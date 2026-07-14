<?php

namespace Modules\Workspace\Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Workspace\Enums\AiActionStatus;
use Modules\Workspace\Events\AiActionUpdated;
use Modules\Workspace\Events\MessageSent;
use Modules\Workspace\Models\AiAction;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Message;
use Modules\Workspace\Models\Project;
use Modules\Workspace\Models\Task;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiActionApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(function (string $modelName) {
            if ($modelName === 'App\\Models\\User') {
                return 'Database\\Factories\\UserFactory';
            }

            return 'Modules\\Workspace\\Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        Config::set('chat_ai.user_email', 'ai@lumi.internal');
        Config::set('chat_ai.action_ttl_minutes', 15);
    }

    private function createBotUser(): User
    {
        return User::factory()->create([
            'email' => 'ai@lumi.internal',
            'name' => 'Lumi AI',
            'role' => UserRole::Employee,
            'is_active' => true,
        ]);
    }

    private function conversationWith(User ...$users): Conversation
    {
        $conversation = Conversation::factory()->create([
            'created_by' => $users[0]->id,
        ]);
        $conversation->participants()->attach(collect($users)->pluck('id'));

        return $conversation;
    }

    private function createPendingTaskAction(User $requester, Conversation $conversation): AiAction
    {
        $bot = $this->createBotUser();
        $project = Project::query()->create([
            'name' => 'Ops',
            'deadline' => '2026-08-01',
            'description' => 'Desc',
            'status' => 'in_progress',
        ]);

        $action = AiAction::query()->create([
            'conversation_id' => $conversation->id,
            'requested_by_user_id' => $requester->id,
            'tool_name' => 'create_task',
            'arguments' => [
                'title' => 'AI task',
                'description' => 'Created via AI',
                'status' => 'to_do',
                'due_date' => '2026-07-25',
                'project_id' => $project->id,
            ],
            'summary' => 'Create task: AI task',
            'status' => AiActionStatus::Pending,
            'expires_at' => now()->addMinutes(15),
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $bot->id,
            'message' => 'Please confirm',
            'type' => 'ai_action',
            'meta' => [
                'action_id' => $action->id,
                'tool_name' => 'create_task',
                'summary' => $action->summary,
                'arguments' => $action->arguments,
                'status' => AiActionStatus::Pending->value,
                'requested_by_user_id' => $requester->id,
                'requested_by_name' => $requester->name,
                'expires_at' => $action->expires_at->toISOString(),
            ],
        ]);

        $action->update(['message_id' => $message->id]);

        return $action->fresh(['message']);
    }

    #[Test]
    public function requester_can_approve_and_create_task(): void
    {
        Event::fake([MessageSent::class, AiActionUpdated::class]);

        $requester = User::factory()->create(['role' => UserRole::Employee]);
        $other = User::factory()->create(['role' => UserRole::Employee]);
        $conversation = $this->conversationWith($requester, $other);
        $action = $this->createPendingTaskAction($requester, $conversation);

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/workspace/conversations/{$conversation->id}/ai-actions/{$action->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('tasks', ['title' => 'AI task']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai_task_create']);

        Event::assertDispatched(AiActionUpdated::class);
        Event::assertDispatched(MessageSent::class);
    }

    #[Test]
    public function other_participant_cannot_approve(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Employee]);
        $other = User::factory()->create(['role' => UserRole::Employee]);
        $conversation = $this->conversationWith($requester, $other);
        $action = $this->createPendingTaskAction($requester, $conversation);

        Sanctum::actingAs($other);

        $this->postJson("/api/v1/workspace/conversations/{$conversation->id}/ai-actions/{$action->id}/approve")
            ->assertForbidden();

        $this->assertDatabaseCount('tasks', 0);
    }

    #[Test]
    public function wrong_conversation_returns_not_found(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Employee]);
        $other = User::factory()->create(['role' => UserRole::Employee]);
        $conversation = $this->conversationWith($requester, $other);
        $otherConversation = $this->conversationWith($requester, $other);
        $action = $this->createPendingTaskAction($requester, $conversation);

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/workspace/conversations/{$otherConversation->id}/ai-actions/{$action->id}/approve")
            ->assertNotFound();
    }

    #[Test]
    public function expired_action_returns_gone(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Employee]);
        $conversation = $this->conversationWith($requester);
        $action = $this->createPendingTaskAction($requester, $conversation);
        $action->update(['expires_at' => now()->subMinute()]);

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/workspace/conversations/{$conversation->id}/ai-actions/{$action->id}/approve")
            ->assertGone();

        $this->assertDatabaseHas('ai_actions', [
            'id' => $action->id,
            'status' => AiActionStatus::Expired->value,
        ]);
    }

    #[Test]
    public function requester_can_reject(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Employee]);
        $conversation = $this->conversationWith($requester);
        $action = $this->createPendingTaskAction($requester, $conversation);

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/workspace/conversations/{$conversation->id}/ai-actions/{$action->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseCount('tasks', 0);
    }

    #[Test]
    public function double_approve_is_idempotent(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Employee]);
        $conversation = $this->conversationWith($requester);
        $action = $this->createPendingTaskAction($requester, $conversation);

        Sanctum::actingAs($requester);

        $url = "/api/v1/workspace/conversations/{$conversation->id}/ai-actions/{$action->id}/approve";

        $this->postJson($url)->assertOk();
        $this->postJson($url)->assertOk();

        $this->assertDatabaseCount('tasks', 1);
    }
}
