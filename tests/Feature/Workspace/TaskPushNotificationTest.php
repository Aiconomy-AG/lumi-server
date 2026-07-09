<?php

namespace Tests\Feature\Workspace;

use App\Enums\UserRole;
use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Modules\Workspace\Models\Project;
use Modules\Workspace\Models\Task;
use Tests\TestCase;

class TaskPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_new_user_to_task_dispatches_push_job(): void
    {
        Queue::fake();

        $actor = User::factory()->create(['role' => UserRole::Employee]);
        $recipient = User::factory()->create(['role' => UserRole::Employee]);
        $task = $this->createTask('Wire notifications');

        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/workspace/tasks/{$task->id}/assignees", [
            'employee_ids' => [$recipient->id],
        ])->assertOk();

        Queue::assertPushed(
            SendPushNotificationJob::class,
            fn (SendPushNotificationJob $job): bool => $job->userId === $recipient->id
                && $job->title === 'Task assigned'
                && $job->body === 'You were assigned: Wire notifications'
                && $job->data === ['type' => 'task_assigned', 'task_id' => (string) $task->id]
        );
    }

    public function test_assigner_is_not_notified_when_assigning_self(): void
    {
        Queue::fake();

        $actor = User::factory()->create(['role' => UserRole::Employee]);
        $task = $this->createTask('Self assignment');

        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/workspace/tasks/{$task->id}/assignees", [
            'employee_ids' => [$actor->id],
        ])->assertOk();

        Queue::assertNotPushed(SendPushNotificationJob::class);
    }

    public function test_already_assigned_user_is_not_notified_again(): void
    {
        Queue::fake();

        $actor = User::factory()->create(['role' => UserRole::Employee]);
        $recipient = User::factory()->create(['role' => UserRole::Employee]);
        $task = $this->createTask('Repeat assignment');
        $task->assignees()->attach($recipient->id);

        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/workspace/tasks/{$task->id}/assignees", [
            'employee_ids' => [$recipient->id],
        ])->assertOk();

        Queue::assertNotPushed(SendPushNotificationJob::class);
    }

    public function test_task_creation_with_employee_ids_dispatches_push_for_non_actor_assignees(): void
    {
        Queue::fake();

        $actor = User::factory()->create(['role' => UserRole::Employee]);
        $recipient = User::factory()->create(['role' => UserRole::Employee]);
        $project = $this->createProject();

        Sanctum::actingAs($actor);

        $response = $this->postJson('/api/v1/workspace/tasks', [
            'title' => 'Create with assignees',
            'description' => 'Attach employees as part of task creation.',
            'status' => 'to_do',
            'due_date' => '2026-07-20',
            'project_id' => $project->id,
            'employee_ids' => [$actor->id, $recipient->id],
        ])->assertCreated();

        $taskId = $response->json('data.id');

        Queue::assertPushed(
            SendPushNotificationJob::class,
            fn (SendPushNotificationJob $job): bool => $job->userId === $recipient->id
                && $job->data === ['type' => 'task_assigned', 'task_id' => (string) $taskId]
        );

        Queue::assertNotPushed(
            SendPushNotificationJob::class,
            fn (SendPushNotificationJob $job): bool => $job->userId === $actor->id
        );
    }

    public function test_unassigning_user_from_task_dispatches_push_job(): void
    {
        Queue::fake();

        $actor = User::factory()->create(['role' => UserRole::Employee]);
        $recipient = User::factory()->create(['role' => UserRole::Employee]);
        $task = $this->createTask('Remove assignee');
        $task->assignees()->attach($recipient->id);

        Sanctum::actingAs($actor);

        $this->deleteJson("/api/v1/workspace/tasks/{$task->id}/assignees/{$recipient->id}")
            ->assertOk();

        Queue::assertPushed(
            SendPushNotificationJob::class,
            fn (SendPushNotificationJob $job): bool => $job->userId === $recipient->id
                && $job->title === 'Task unassigned'
                && $job->body === 'You were unassigned from: Remove assignee'
                && $job->data === ['type' => 'task_unassigned', 'task_id' => (string) $task->id]
        );
    }

    public function test_unassigning_self_does_not_dispatch_push_job_to_actor(): void
    {
        Queue::fake();

        $actor = User::factory()->create(['role' => UserRole::Employee]);
        $task = $this->createTask('Self unassign');
        $task->assignees()->attach($actor->id);

        Sanctum::actingAs($actor);

        $this->deleteJson("/api/v1/workspace/tasks/{$task->id}/assignees/{$actor->id}")
            ->assertOk();

        Queue::assertNotPushed(SendPushNotificationJob::class);
    }

    public function test_status_change_dispatches_push_to_assignees_except_actor(): void
    {
        Queue::fake();

        $actor = User::factory()->create(['role' => UserRole::Employee]);
        $recipient = User::factory()->create(['role' => UserRole::Employee]);
        $task = $this->createTask('Change status');
        $task->assignees()->attach([$actor->id, $recipient->id]);

        Sanctum::actingAs($actor);

        $this->putJson("/api/v1/workspace/tasks/{$task->id}", [
            'title' => $task->title,
            'description' => $task->description,
            'status' => 'in_progress',
            'due_date' => $task->due_date->toDateString(),
            'project_id' => $task->project_id,
        ])->assertOk();

        Queue::assertPushed(
            SendPushNotificationJob::class,
            fn (SendPushNotificationJob $job): bool => $job->userId === $recipient->id
                && $job->title === 'Task status changed'
                && $job->body === 'Task status changed: Change status'
                && $job->data === ['type' => 'task_status_changed', 'task_id' => (string) $task->id]
        );

        Queue::assertNotPushed(
            SendPushNotificationJob::class,
            fn (SendPushNotificationJob $job): bool => $job->userId === $actor->id
        );
    }

    private function createTask(string $title): Task
    {
        return Task::query()->create([
            'title' => $title,
            'description' => 'Task description',
            'status' => 'to_do',
            'due_date' => '2026-07-20',
            'project_id' => $this->createProject()->id,
        ]);
    }

    private function createProject(): Project
    {
        return Project::query()->create([
            'name' => 'PM module',
            'deadline' => '2026-07-30',
            'description' => 'Project management work',
            'status' => 'in_progress',
        ]);
    }
}
