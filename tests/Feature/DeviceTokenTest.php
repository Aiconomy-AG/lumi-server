<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DeviceToken;
use App\Models\User;
use App\Support\DeviceTokenPlatform;
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
            'device_id' => 'device-1',
        ])
            ->assertCreated()
            ->assertJsonPath('data.platform', DeviceTokenPlatform::FCM_ANDROID)
            ->assertJsonPath('data.device_id', 'device-1');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-1',
            'platform' => DeviceTokenPlatform::FCM_ANDROID,
            'device_id' => 'device-1',
        ]);
    }

    public function test_registering_same_device_updates_token(): void
    {
        $user = User::factory()->create(['role' => UserRole::Employee]);

        DeviceToken::query()->create([
            'user_id' => $user->id,
            'token' => 'old-token',
            'platform' => DeviceTokenPlatform::FCM_ANDROID,
            'device_id' => 'device-1',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/device-tokens', [
            'token' => 'new-token',
            'platform' => 'fcm_android',
            'device_id' => 'device-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.platform', DeviceTokenPlatform::FCM_ANDROID);

        $this->assertSame(1, DeviceToken::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'new-token',
            'platform' => DeviceTokenPlatform::FCM_ANDROID,
            'device_id' => 'device-1',
        ]);
    }

    public function test_user_can_register_apns_voip_token(): void
    {
        $user = User::factory()->create(['role' => UserRole::Employee]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/device-tokens', [
            'token' => 'voip-token',
            'platform' => 'apns_voip',
            'device_id' => 'iphone-1',
        ])
            ->assertCreated()
            ->assertJsonPath('data.platform', DeviceTokenPlatform::APNS_VOIP);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'platform' => DeviceTokenPlatform::APNS_VOIP,
            'device_id' => 'iphone-1',
        ]);
    }

    public function test_user_cannot_delete_another_users_token(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Employee]);
        $otherUser = User::factory()->create(['role' => UserRole::Employee]);

        $token = DeviceToken::query()->create([
            'user_id' => $owner->id,
            'token' => 'owned-token',
            'platform' => DeviceTokenPlatform::FCM_ANDROID,
            'device_id' => 'device-owner',
        ]);

        Sanctum::actingAs($otherUser);

        $this->deleteJson('/api/v1/device-tokens/'.$token->id)->assertNoContent();

        $this->assertDatabaseHas('device_tokens', [
            'id' => $token->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_user_can_delete_own_token_by_id(): void
    {
        $user = User::factory()->create(['role' => UserRole::Employee]);

        $token = DeviceToken::query()->create([
            'user_id' => $user->id,
            'token' => 'own-token',
            'platform' => DeviceTokenPlatform::FCM_ANDROID,
            'device_id' => 'device-own',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/device-tokens/'.$token->id)->assertNoContent();

        $this->assertDatabaseMissing('device_tokens', [
            'id' => $token->id,
        ]);
    }

    public function test_user_can_delete_own_token_by_body(): void
    {
        $user = User::factory()->create(['role' => UserRole::Employee]);

        DeviceToken::query()->create([
            'user_id' => $user->id,
            'token' => 'own-token',
            'platform' => DeviceTokenPlatform::FCM_ANDROID,
            'device_id' => 'device-own',
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
