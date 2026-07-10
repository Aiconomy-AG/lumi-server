<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_change_writes_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::Employee]);
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/admin/users/{$user->id}", [
            'role' => 'admin',
        ])->assertOk();

        $log = AuditLog::where('action', 'role_change')->sole();
        $this->assertSame('users', $log->module);
        $this->assertSame('employee', $log->changes['old']['role']);
        $this->assertSame('admin', $log->changes['new']['role']);
    }

    public function test_deactivate_writes_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::Employee]);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/admin/users/{$user->id}")
            ->assertNoContent();

        $log = AuditLog::where('action', 'deactivate')->sole();
        $this->assertSame('users', $log->module);
        $this->assertSame($user->email, $log->entity_label);
    }

    public function test_password_change_writes_audit_log_without_password_value(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::Employee]);
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/admin/users/{$user->id}", [
            'password' => 'newsecret',
        ])->assertOk();

        $log = AuditLog::where('action', 'password_reset')->sole();
        $this->assertSame('users', $log->module);
        $this->assertNull($log->changes);
        $this->assertStringContainsString('Password', (string) $log->description);
    }

    public function test_resend_invite_writes_audit_log(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'role' => UserRole::Employee,
            'must_change_password' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/users/{$user->id}/resend-invite")
            ->assertOk();

        $log = AuditLog::where('action', 'invite_resent')->sole();
        $this->assertSame('users', $log->module);
        $this->assertNull($log->changes);
    }
}
