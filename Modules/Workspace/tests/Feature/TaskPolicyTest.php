<?php

namespace Modules\Workspace\Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Project;
use Modules\Workspace\Models\Task;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function createProject(): Project
    {
        return Project::query()->create([
            'name' => 'Test project',
            'deadline' => '2026-08-01',
            'description' => 'Description',
            'status' => 'in_progress',
        ]);
    }

    private function createTask(?Project $project = null): Task
    {
        return Task::query()->create([
            'title' => 'Test task',
            'description' => 'Task description',
            'status' => 'to_do',
            'due_date' => '2026-07-20',
            'project_id' => ($project ?? $this->createProject())->id,
        ]);
    }

    #[Test]
    public function client_cannot_create_task(): void
    {
        $client = User::factory()->create(['role' => UserRole::Client]);
        Sanctum::actingAs($client);

        $this->postJson('/api/v1/workspace/tasks', [
            'title' => 'Blocked task',
            'description' => 'Should not be created',
            'status' => 'to_do',
            'due_date' => '2026-07-20',
            'project_id' => $this->createProject()->id,
        ])->assertForbidden();
    }

    #[Test]
    public function client_cannot_list_tasks(): void
    {
        $client = User::factory()->create(['role' => UserRole::Client]);
        Sanctum::actingAs($client);

        $this->getJson('/api/v1/workspace/tasks')->assertForbidden();
    }

    #[Test]
    public function client_cannot_update_task(): void
    {
        $client = User::factory()->create(['role' => UserRole::Client]);
        $task = $this->createTask();
        Sanctum::actingAs($client);

        $this->putJson("/api/v1/workspace/tasks/{$task->id}", [
            'title' => 'Updated',
            'description' => $task->description,
            'status' => 'in_progress',
            'due_date' => $task->due_date->toDateString(),
            'project_id' => $task->project_id,
        ])->assertForbidden();
    }

    #[Test]
    public function client_cannot_delete_task(): void
    {
        $client = User::factory()->create(['role' => UserRole::Client]);
        $task = $this->createTask();
        Sanctum::actingAs($client);

        $this->deleteJson("/api/v1/workspace/tasks/{$task->id}")->assertForbidden();
    }

    #[Test]
    public function employee_can_create_task(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/workspace/tasks', [
            'title' => 'Allowed task',
            'description' => 'Created by employee',
            'status' => 'to_do',
            'due_date' => '2026-07-20',
            'project_id' => $this->createProject()->id,
        ])->assertCreated();
    }

    #[Test]
    public function soft_delete_recursively_deletes_subtasks(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $project = $this->createProject();
        $parent = $this->createTask($project);
        $child = Task::query()->create([
            'title' => 'Subtask',
            'description' => 'Child task',
            'status' => 'to_do',
            'due_date' => '2026-07-21',
            'project_id' => $project->id,
            'parent_id' => $parent->id,
        ]);

        Sanctum::actingAs($employee);

        $this->deleteJson("/api/v1/workspace/tasks/{$parent->id}")->assertOk();

        $this->assertSoftDeleted('tasks', ['id' => $parent->id]);
        $this->assertSoftDeleted('tasks', ['id' => $child->id]);
    }
}
