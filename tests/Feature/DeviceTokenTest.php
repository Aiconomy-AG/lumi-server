<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_register_device_token(): void
    {
        $user = User::factory()->create(['role' => UserRole::Employee]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-1',
            'platform' => 'android',
        ])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-1',
            'platform' => 'android',
        ]);
    }

    public function test_registering_same_token_updates_owner_and_platform(): void
    {
        $originalUser = User::factory()->create(['role' => UserRole::Employee]);
        $newUser = User::factory()->create(['role' => UserRole::Employee]);

        DeviceToken::query()->create([
            'user_id' => $originalUser->id,
            'token' => 'shared-fcm-token',
            'platform' => 'android',
        ]);

        Sanctum::actingAs($newUser);

        $this->postJson('/api/v1/device-tokens', [
            'token' => 'shared-fcm-token',
            'platform' => 'ios',
        ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'ios');

        $this->assertSame(1, DeviceToken::query()->where('token', 'shared-fcm-token')->count());
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $newUser->id,
            'token' => 'shared-fcm-token',
            'platform' => 'ios',
        ]);
    }

    public function test_user_cannot_delete_another_users_token(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Employee]);
        $otherUser = User::factory()->create(['role' => UserRole::Employee]);

        DeviceToken::query()->create([
            'user_id' => $owner->id,
            'token' => 'owned-token',
            'platform' => 'android',
        ]);

        Sanctum::actingAs($otherUser);

        $this->deleteJson('/api/v1/device-tokens', [
            'token' => 'owned-token',
        ])->assertNoContent();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $owner->id,
            'token' => 'owned-token',
        ]);
    }

    public function test_user_can_delete_own_token(): void
    {
        $user = User::factory()->create(['role' => UserRole::Employee]);

        DeviceToken::query()->create([
            'user_id' => $user->id,
            'token' => 'own-token',
            'platform' => 'android',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/device-tokens', [
            'token' => 'own-token',
        ])->assertNoContent();

        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'own-token',
        ]);
    }
}
