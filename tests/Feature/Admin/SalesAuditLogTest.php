<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Modules\Sales\Models\ReturnRequest;
use Tests\TestCase;

class SalesAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_update_writes_audit_log(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => UserRole::Employee]);
        Sanctum::actingAs($user);

        $product = Product::factory()->create(['name' => 'Old Name']);

        $this->putJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'New Name',
        ])->assertOk();

        $log = AuditLog::where('action', 'update')->sole();
        $this->assertSame('sales', $log->module);
        $this->assertSame('Old Name', $log->changes['old']['name']);
        $this->assertSame('New Name', $log->changes['new']['name']);
    }

    public function test_variant_put_stock_change_writes_stock_update_audit_log(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => UserRole::Employee]);
        Sanctum::actingAs($user);

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock_quantity' => 5,
        ]);

        $this->putJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}", [
            'stock_quantity' => 12,
        ])->assertOk();

        $log = AuditLog::where('action', 'stock_update')->sole();
        $this->assertSame('sales', $log->module);
        $this->assertSame(5, $log->changes['old']['stock_quantity']);
        $this->assertSame(12, $log->changes['new']['stock_quantity']);
    }

    public function test_return_status_change_writes_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $returnRequest = ReturnRequest::query()->create([
            'email' => 'buyer@example.com',
            'reason' => 'Damaged item',
            'status' => ReturnRequest::STATUS_REQUESTED,
            'items' => [],
        ]);

        $this->patchJson("/api/v1/admin/returns/{$returnRequest->id}", [
            'status' => ReturnRequest::STATUS_APPROVED,
        ])->assertOk();

        $log = AuditLog::where('action', 'return_status_change')->sole();
        $this->assertSame('sales', $log->module);
        $this->assertSame(ReturnRequest::STATUS_REQUESTED, $log->changes['old']['status']);
        $this->assertSame(ReturnRequest::STATUS_APPROVED, $log->changes['new']['status']);
    }
}
