<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_access_admin_user_routes(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employee]));

        $this->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_admin_can_create_update_and_deactivate_user(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/v1/admin/users', [
            'name' => 'Jane Employee',
            'email' => 'jane@example.com',
            'password' => 'secret123',
            'role' => 'employee',
            'status' => 'available',
            'phone_number' => '123',
            'language_flag' => 'en',
        ]);

        $createResponse->assertCreated();
        $userId = $createResponse->json('data.id');

        $this->putJson("/api/v1/admin/users/{$userId}", [
            'name' => 'Jane Updated',
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.name', 'Jane Updated');

        $this->deleteJson("/api/v1/admin/users/{$userId}")
            ->assertNoContent();

        $this->assertFalse((bool) User::findOrFail($userId)->is_active);
    }

    public function test_invalid_role_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/admin/users', [
            'name' => 'Bad Role',
            'email' => 'bad@example.com',
            'password' => 'secret123',
            'role' => 'manager',
        ])->assertUnprocessable();
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/admin/users/{$admin->id}")
            ->assertStatus(422);
    }
}
