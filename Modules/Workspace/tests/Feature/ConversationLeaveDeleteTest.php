<?php

namespace Modules\Workspace\Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Workspace\Events\ConversationDeleted;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Message;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConversationLeaveDeleteTest extends TestCase
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
    }

    private function groupWith(User $creator, User ...$others): Conversation
    {
        $conversation = Conversation::factory()->create([
            'type' => 'group',
            'name' => 'Test Group',
            'created_by' => $creator->id,
        ]);

        $conversation->participants()->attach(
            collect([$creator, ...$others])->pluck('id')
        );

        return $conversation;
    }

    #[Test]
    public function participant_can_leave_a_group(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Employee]);
        $member = User::factory()->create(['role' => UserRole::Employee]);
        $third = User::factory()->create(['role' => UserRole::Employee]);
        $group = $this->groupWith($creator, $member, $third);

        Sanctum::actingAs($member);

        $this->postJson("/api/v1/workspace/conversations/{$group->id}/leave")
            ->assertOk();

        $this->assertFalse(
            $group->participants()->whereKey($member->id)->exists(),
            'Member should have been detached from the group.'
        );
        $this->assertTrue(
            $group->participants()->whereKey($creator->id)->exists(),
            'Other participants should be untouched.'
        );
    }

    #[Test]
    public function leaving_posts_a_system_message(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Employee]);
        $member = User::factory()->create(['name' => 'Ada', 'role' => UserRole::Employee]);
        $group = $this->groupWith($creator, $member);

        Sanctum::actingAs($member);

        $this->postJson("/api/v1/workspace/conversations/{$group->id}/leave")->assertOk();

        $message = Message::query()->where('conversation_id', $group->id)->latest('id')->first();

        $this->assertNotNull($message);
        $this->assertStringContainsString('Ada left the group', $message->message);
        $this->assertSame('system', $message->message_type->value);
    }

    #[Test]
    public function leaving_is_allowed_even_when_it_drops_the_group_below_two_participants(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Employee]);
        $member = User::factory()->create(['role' => UserRole::Employee]);
        $group = $this->groupWith($creator, $member);

        Sanctum::actingAs($member);

        $this->postJson("/api/v1/workspace/conversations/{$group->id}/leave")
            ->assertOk();

        $this->assertSame(1, $group->participants()->count());
    }

    #[Test]
    public function non_participant_cannot_leave_a_group(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Employee]);
        $outsider = User::factory()->create(['role' => UserRole::Employee]);
        $group = $this->groupWith($creator, User::factory()->create());

        Sanctum::actingAs($outsider);

        $this->postJson("/api/v1/workspace/conversations/{$group->id}/leave")
            ->assertForbidden();
    }

    #[Test]
    public function direct_conversations_cannot_be_left(): void
    {
        $user = User::factory()->create(['role' => UserRole::Employee]);
        $other = User::factory()->create(['role' => UserRole::Employee]);

        $direct = Conversation::factory()->create([
            'type' => 'direct',
            'created_by' => $user->id,
        ]);
        $direct->participants()->attach([$user->id, $other->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/workspace/conversations/{$direct->id}/leave")
            ->assertForbidden();
    }

    #[Test]
    public function creator_can_delete_their_own_group(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Employee]);
        $group = $this->groupWith($creator, User::factory()->create());

        Sanctum::actingAs($creator);

        $this->deleteJson("/api/v1/workspace/conversations/{$group->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('conversations', ['id' => $group->id]);
    }

    #[Test]
    public function app_admin_can_delete_a_group_they_are_not_part_of(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Employee]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $group = $this->groupWith($creator, User::factory()->create());

        $this->assertFalse($group->participants()->whereKey($admin->id)->exists());

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/workspace/conversations/{$group->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('conversations', ['id' => $group->id]);
    }

    #[Test]
    public function ordinary_participant_cannot_delete_a_group(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Employee]);
        $member = User::factory()->create(['role' => UserRole::Employee]);
        $group = $this->groupWith($creator, $member);

        Sanctum::actingAs($member);

        $this->deleteJson("/api/v1/workspace/conversations/{$group->id}")
            ->assertForbidden();

        $this->assertNotSoftDeleted('conversations', ['id' => $group->id]);
    }

    #[Test]
    public function direct_conversations_cannot_be_deleted_even_by_an_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $other = User::factory()->create(['role' => UserRole::Employee]);

        $direct = Conversation::factory()->create([
            'type' => 'direct',
            'created_by' => $admin->id,
        ]);
        $direct->participants()->attach([$admin->id, $other->id]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/workspace/conversations/{$direct->id}")
            ->assertForbidden();
    }

    #[Test]
    public function deleting_a_group_preserves_its_messages(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Employee]);
        $group = $this->groupWith($creator, User::factory()->create());

        $message = Message::query()->create([
            'conversation_id' => $group->id,
            'sender_id' => $creator->id,
            'message' => 'keep me',
        ]);

        Sanctum::actingAs($creator);

        $this->deleteJson("/api/v1/workspace/conversations/{$group->id}")->assertNoContent();

        $this->assertDatabaseHas('messages', ['id' => $message->id, 'message' => 'keep me']);
    }

    #[Test]
    public function a_deleted_group_disappears_from_the_conversation_list_and_returns_404(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Employee]);
        $member = User::factory()->create(['role' => UserRole::Employee]);
        $group = $this->groupWith($creator, $member);

        Sanctum::actingAs($creator);
        $this->deleteJson("/api/v1/workspace/conversations/{$group->id}")->assertNoContent();

        $this->getJson("/api/v1/workspace/conversations/{$group->id}")->assertNotFound();

        Sanctum::actingAs($member);
        $list = $this->getJson('/api/v1/workspace/conversations')->assertOk()->json('data');

        $this->assertEmpty(
            collect($list)->where('id', $group->id)->all(),
            'Deleted group should not appear for remaining participants.'
        );
    }

    #[Test]
    public function deleting_a_group_broadcasts_and_writes_an_audit_log(): void
    {
        Event::fake([ConversationDeleted::class]);

        $creator = User::factory()->create(['role' => UserRole::Employee]);
        $group = $this->groupWith($creator, User::factory()->create());

        Sanctum::actingAs($creator);
        $this->deleteJson("/api/v1/workspace/conversations/{$group->id}")->assertNoContent();

        Event::assertDispatched(
            ConversationDeleted::class,
            fn (ConversationDeleted $event) => $event->conversationId === $group->id
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'conversation_delete',
            'actor_user_id' => $creator->id,
        ]);
    }

    #[Test]
    public function leaving_writes_an_audit_log(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Employee]);
        $member = User::factory()->create(['role' => UserRole::Employee]);
        $group = $this->groupWith($creator, $member);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/workspace/conversations/{$group->id}/leave")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'conversation_leave',
            'actor_user_id' => $member->id,
        ]);
    }
}
