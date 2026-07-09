<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_login_returns_token_and_user_payload(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Employee,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role', 'status', 'phone_number', 'language_flag', 'is_active', 'must_change_password'],
            ]);
    }

    public function test_client_cannot_login_to_panel(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Client,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Employee,
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_user_that_must_change_password_cannot_login(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Employee,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_me_requires_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_time_entries_routes_require_token(): void
    {
        $this->getJson('/api/v1/workspace/tasks/1/time-entries')->assertUnauthorized();
        $this->postJson('/api/v1/workspace/tasks/1/time-entries/start')->assertUnauthorized();
        $this->postJson('/api/v1/workspace/tasks/1/time-entries/1/stop')->assertUnauthorized();
    }

    public function test_me_status_update_requires_token(): void
    {
        $this->patchJson('/api/v1/auth/me/status', [
            'status' => 'busy',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_update_own_status(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Employee,
            'is_active' => true,
            'status' => 'offline',
        ]);

        $this->actingAs($user, 'sanctum');

        $this->patchJson('/api/v1/auth/me/status', [
            'status' => 'busy',
        ])->assertOk()->assertJsonPath('data.status', 'busy');

        $this->assertSame('busy', $user->fresh()->status);
    }

    public function test_authenticated_user_cannot_set_status_to_offline(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Employee,
            'is_active' => true,
            'status' => 'available',
        ]);

        $this->actingAs($user, 'sanctum');

        $this->patchJson('/api/v1/auth/me/status', [
            'status' => 'offline',
        ])->assertUnprocessable();
    }
}
