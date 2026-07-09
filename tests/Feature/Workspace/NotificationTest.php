<?php

namespace Tests\Feature\Workspace;

use App\Enums\UserRole;
use App\Events\NotificationDelivered;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Workspace\Models\Project;
use Modules\Workspace\Models\Task;
use Modules\Workspace\Services\NotificationService;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_and_mark_own_notifications_as_read(): void
    {
        Event::fake([NotificationDelivered::class]);

        $actor = User::factory()->create(['role' => UserRole::Employee]);
        $recipient = User::factory()->create(['role' => UserRole::Employee]);

        app(NotificationService::class)->createForRecipients(
            type: 'task_due_date_changed',
            source: 'task',
            recipientUserIds: [$recipient->id],
            actorUserId: $actor->id,
            payload: [
                'field' => 'due_date',
                'old_value' => '2026-07-20',
                'new_value' => '2026-07-25',
            ],
        );

        Sanctum::actingAs($recipient);

        $listResponse = $this->getJson('/api/v1/workspace/notifications?unreadOnly=true')
            ->assertOk()
            ->assertJsonPath('data.0.event.type', 'task_due_date_changed')
            ->assertJsonPath('data.0.read_at', null);

        $notificationId = $listResponse->json('data.0.id');

        $this->putJson("/api/v1/workspace/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notificationId)
            ->assertJsonPath('data.event.type', 'task_due_date_changed');

        $this->assertDatabaseMissing('notification_deliveries', [
            'id' => $notificationId,
            'read_at' => null,
        ]);
    }

    public function test_assigning_user_to_task_creates_live_notification_delivery(): void
    {
        Event::fake([NotificationDelivered::class]);

        $actor = User::factory()->create(['role' => UserRole::Employee]);
        $recipient = User::factory()->create(['role' => UserRole::Employee]);
        $project = Project::query()->create([
            'name' => 'PM module',
            'deadline' => '2026-07-30',
            'description' => 'Project management work',
            'status' => 'in_progress',
        ]);
        $task = Task::query()->create([
            'title' => 'Wire notifications',
            'description' => 'Create notification deliveries for assigned users.',
            'status' => 'to_do',
            'due_date' => '2026-07-20',
            'project_id' => $project->id,
        ]);

        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/workspace/tasks/{$task->id}/assignees", [
            'employee_ids' => [$recipient->id],
        ])->assertOk();

        $this->assertDatabaseHas('notification_events', [
            'actor_user_id' => $actor->id,
            'type' => 'task_assigned',
            'source' => 'task',
            'task_id' => $task->id,
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'recipient_user_id' => $recipient->id,
            'read_at' => null,
        ]);

        Event::assertDispatched(NotificationDelivered::class);
    }
}
