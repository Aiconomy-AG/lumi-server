<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_audit_logs(): void
    {
        $this->getJson('/api/v1/admin/audit-logs')->assertUnauthorized();
    }

    public function test_employee_cannot_list_audit_logs(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employee]));

        $this->getJson('/api/v1/admin/audit-logs')->assertForbidden();
    }

    public function test_admin_can_list_and_filter_audit_logs(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        AuditLog::create([
            'module' => 'sales',
            'action' => 'stock_update',
            'entity_type' => 'product_variants',
            'entity_id' => 1,
            'entity_label' => 'Honig (HON-1)',
            'actor_user_id' => $admin->id,
            'actor_name' => $admin->name,
            'changes' => ['old' => ['stock_quantity' => 1], 'new' => ['stock_quantity' => 5]],
            'occurred_at' => now(),
        ]);
        AuditLog::create([
            'module' => 'users',
            'action' => 'update',
            'entity_type' => 'users',
            'entity_id' => $admin->id,
            'actor_user_id' => $admin->id,
            'actor_name' => $admin->name,
            'occurred_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/admin/audit-logs')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.module', 'sales')
            ->assertJsonPath('data.0.changes.new.stock_quantity', 5);

        $this->getJson('/api/v1/admin/audit-logs?module=users')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.module', 'users');
    }

    public function test_filters_are_validated(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/admin/audit-logs?per_page=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_stock_update_writes_audit_log(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => UserRole::Employee]);
        Sanctum::actingAs($user);

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => 3,
        ]);

        $this->patchJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}", [
            'stock_quantity' => 10,
            'reason' => 'Inventory recount',
        ])->assertOk();

        $log = AuditLog::sole();
        $this->assertSame('sales', $log->module);
        $this->assertSame('stock_update', $log->action);
        $this->assertSame($variant->id, (int) $log->entity_id);
        $this->assertSame($user->id, (int) $log->actor_user_id);
        $this->assertSame('Inventory recount', $log->description);
        $this->assertSame(3, $log->changes['old']['stock_quantity']);
        $this->assertSame(10, $log->changes['new']['stock_quantity']);
    }

    public function test_unchanged_stock_writes_no_audit_log(): void
    {
        Queue::fake();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employee]));

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => 3,
        ]);

        $this->patchJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}", [
            'stock_quantity' => 3,
        ])->assertOk();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_user_invite_writes_audit_log(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/users', [
            'email' => 'invited@example.com',
            'role' => 'employee',
        ])->assertCreated();

        $log = AuditLog::where('action', 'create')->sole();
        $this->assertSame('users', $log->module);
        $this->assertSame($admin->id, (int) $log->actor_user_id);
        $this->assertSame('invited@example.com', $log->entity_label);
    }

    public function test_login_writes_audit_log(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Employee,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertCreated();

        $log = AuditLog::where('action', 'login')->sole();
        $this->assertSame('auth', $log->module);
        $this->assertSame($user->id, (int) $log->actor_user_id);
    }
}
