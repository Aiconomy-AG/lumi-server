<?php

namespace Tests\Feature\Workspace;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Workspace\Models\Project;
use Modules\Workspace\Models\Task;
use Tests\TestCase;

class WorkspaceAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_create_writes_audit_log(): void
    {
        $user = User::factory()->create(['role' => UserRole::Employee]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspace/projects', [
            'name' => 'New project',
            'deadline' => '2026-08-01',
            'description' => 'Project description',
            'status' => 'in_progress',
        ])->assertCreated();

        $log = AuditLog::where('action', 'project_create')->sole();
        $this->assertSame('workspace', $log->module);
        $this->assertSame($user->id, (int) $log->actor_user_id);
        $this->assertSame('New project', $log->changes['new']['name']);
    }

    public function test_task_assign_writes_audit_log(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Employee]);
        $recipient = User::factory()->create(['role' => UserRole::Employee]);
        $task = $this->createTask('Assign audit test');

        Sanctum::actingAs($actor);

        $this->postJson("/api/v1/workspace/tasks/{$task->id}/assignees", [
            'employee_ids' => [$recipient->id],
        ])->assertOk();

        $log = AuditLog::where('action', 'task_assign')->sole();
        $this->assertSame('workspace', $log->module);
        $this->assertSame([$recipient->id], $log->changes['new']['employee_ids']);
    }

    public function test_time_start_writes_audit_log(): void
    {
        $user = User::factory()->create(['role' => UserRole::Employee]);
        $task = $this->createTask('Timer audit test');

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/workspace/tasks/{$task->id}/time-entries/start")
            ->assertCreated();

        $log = AuditLog::where('action', 'time_start')->sole();
        $this->assertSame('workspace', $log->module);
        $this->assertSame($task->id, (int) $log->changes['new']['task_id']);
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
