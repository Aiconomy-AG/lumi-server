<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PresenceExpiryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_marks_stale_users_offline(): void
    {
        config()->set('presence.offline_ttl_seconds', 90);

        $staleUser = User::factory()->create([
            'role' => UserRole::Employee,
            'status' => 'available',
            'last_seen_at' => Carbon::now()->subMinutes(5),
        ]);
        $freshUser = User::factory()->create([
            'role' => UserRole::Employee,
            'status' => 'busy',
            'last_seen_at' => Carbon::now()->subSeconds(30),
        ]);

        $this->artisan('presence:expire-stale')->assertSuccessful();

        $this->assertSame('offline', $staleUser->fresh()->status);
        $this->assertSame('busy', $freshUser->fresh()->status);
    }
}
