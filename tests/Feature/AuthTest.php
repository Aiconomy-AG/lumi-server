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

    public function test_authenticated_user_can_update_own_phone_number(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Employee,
            'is_active' => true,
            'phone_number' => '',
        ]);

        $this->actingAs($user, 'sanctum');

        $this->putJson('/api/v1/auth/phone', [
            'phone_number' => '+40722123456',
        ])
            ->assertOk()
            ->assertJson([
                'message' => 'Phone number updated successfully',
                'phone_number' => '+40722123456',
            ]);

        $this->assertSame('+40722123456', $user->fresh()->phone_number);
    }

    public function test_phone_update_requires_token(): void
    {
        $this->putJson('/api/v1/auth/phone', [
            'phone_number' => '+40722123456',
        ])->assertUnauthorized();
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

    public function test_presence_ping_requires_token(): void
    {
        $this->postJson('/api/v1/auth/me/presence/ping')->assertUnauthorized();
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

    public function test_presence_ping_marks_user_alive_and_available(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Employee,
            'is_active' => true,
            'status' => 'offline',
            'last_seen_at' => null,
        ]);

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/v1/auth/me/presence/ping')->assertNoContent();

        $fresh = $user->fresh();
        $this->assertSame('available', $fresh->status);
        $this->assertNotNull($fresh->last_seen_at);
    }

    public function test_disconnect_with_personal_access_token_marks_user_offline(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Employee,
            'is_active' => true,
            'status' => 'busy',
        ]);
        $plainTextToken = $user->createToken('test')->plainTextToken;

        $this->postJson('/api/v1/auth/me/presence/disconnect', [
            'token' => $plainTextToken,
        ])->assertNoContent();

        $this->assertSame('offline', $user->fresh()->status);
    }
}
