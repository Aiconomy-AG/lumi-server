<?php

namespace Modules\Workspace\Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\Order;
use Modules\Sales\Models\OrderItem;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ProductVariant;
use Modules\Sales\Models\ReturnItem;
use Modules\Sales\Models\ReturnRequest;
use Tests\TestCase;

class WorkspaceReturnRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_list_returns_with_pagination_meta(): void
    {
        Sanctum::actingAs(User::factory()->create());

        ReturnRequest::query()->create([
            'email' => 'first@example.com',
            'reason' => 'Damaged',
            'status' => 'requested',
            'items' => [['title' => 'Bath Bomb', 'quantity' => 1]],
        ]);

        ReturnRequest::query()->create([
            'email' => 'second@example.com',
            'reason' => 'Wrong item',
            'status' => 'approved',
            'items' => [['title' => 'Soap', 'quantity' => 2]],
        ]);

        $this->getJson('/api/v1/workspace/returns?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'email',
                        'items',
                        'reason',
                        'notes',
                        'status',
                        'refund_amount',
                        'return_items',
                    ],
                ],
                'meta',
            ]);
    }

    public function test_staff_can_view_a_return_with_return_items(): void
    {
        Sanctum::actingAs(User::factory()->create());

        [$returnRequest, $returnItem] = $this->createReturnWithItem();

        $this->getJson("/api/v1/workspace/returns/{$returnRequest->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $returnRequest->id)
            ->assertJsonPath('data.return_items.0.id', $returnItem->id)
            ->assertJsonPath('data.return_items.0.quantity', 1)
            ->assertJsonStructure([
                'data' => [
                    'return_items' => [
                        '*' => ['id', 'order_item_id', 'quantity', 'order_item'],
                    ],
                ],
            ]);
    }

    public function test_staff_can_update_status_and_notes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $returnRequest = ReturnRequest::query()->create([
            'email' => 'customer@example.com',
            'reason' => 'Damaged',
            'status' => 'requested',
            'notes' => null,
        ]);

        $this->patchJson("/api/v1/workspace/returns/{$returnRequest->id}", [
            'status' => 'approved',
            'notes' => 'Approved for refund.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.notes', 'Approved for refund.');

        $this->assertDatabaseHas('return_requests', [
            'id' => $returnRequest->id,
            'status' => 'approved',
            'notes' => 'Approved for refund.',
        ]);
    }

    public function test_status_update_creates_workspace_audit_log(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $returnRequest = ReturnRequest::query()->create([
            'email' => 'customer@example.com',
            'reason' => 'Damaged',
            'status' => 'requested',
        ]);

        $this->patchJson("/api/v1/workspace/returns/{$returnRequest->id}", [
            'status' => 'rejected',
            'notes' => 'Outside return window.',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'workspace',
            'action' => 'return_status_change',
            'entity_type' => 'return_requests',
            'entity_id' => $returnRequest->id,
            'entity_label' => 'Return #'.$returnRequest->id,
            'actor_user_id' => $user->id,
        ]);

        $auditLog = AuditLog::query()
            ->where('entity_type', 'return_requests')
            ->where('entity_id', $returnRequest->id)
            ->firstOrFail();

        $this->assertSame('requested', $auditLog->changes['old']['status']);
        $this->assertSame('rejected', $auditLog->changes['new']['status']);
    }

    public function test_invalid_status_returns_validation_error(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $returnRequest = ReturnRequest::query()->create([
            'email' => 'customer@example.com',
            'reason' => 'Damaged',
            'status' => 'requested',
        ]);

        $this->patchJson("/api/v1/workspace/returns/{$returnRequest->id}", [
            'status' => 'bogus',
        ])->assertUnprocessable();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/workspace/returns')
            ->assertUnauthorized();
    }

    public function test_client_and_inactive_users_are_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Client]));

        $this->getJson('/api/v1/workspace/returns')
            ->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['is_active' => false]));

        $this->getJson('/api/v1/workspace/returns')
            ->assertForbidden();
    }

    private function createReturnWithItem(): array
    {
        $customer = Customer::query()->create([
            'username' => 'customer1',
            'email' => 'customer@example.com',
            'shopify_customer_id' => 'shop_1',
        ]);

        $product = Product::factory()->create([
            'name' => 'Bath Bomb',
            'price' => 12.5,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 12.5,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'status' => 'pending',
            'subtotal' => 12.5,
            'shipping_cost' => 2.5,
            'total_amount' => 15,
            'shipping_address' => 'Address',
            'payment_method' => 'card',
            'payment_status' => 'unshipped',
        ]);

        $orderItem = OrderItem::unguarded(fn () => OrderItem::query()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 12.5,
        ]));

        $returnRequest = ReturnRequest::query()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'reason' => 'Damaged',
            'status' => 'requested',
            'items' => [['title' => 'Bath Bomb', 'quantity' => 1]],
        ]);

        $returnItem = ReturnItem::query()->create([
            'return_request_id' => $returnRequest->id,
            'order_item_id' => $orderItem->id,
            'quantity' => 1,
        ]);

        return [$returnRequest, $returnItem];
    }
}
