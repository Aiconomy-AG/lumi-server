<?php

namespace Modules\Sales\Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\ReturnRequest;
use Tests\TestCase;

class AdminReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_list_return_requests_with_filters(): void
    {
        Sanctum::actingAs(User::factory()->create());

        ReturnRequest::query()->create([
            'email' => 'approved@example.com',
            'shopify_order_name' => '#1001',
            'reason' => 'damaged',
            'status' => ReturnRequest::STATUS_APPROVED,
        ]);

        ReturnRequest::query()->create([
            'email' => 'requested@example.com',
            'shopify_order_name' => '#1002',
            'reason' => 'wrong item',
            'status' => ReturnRequest::STATUS_REQUESTED,
        ]);

        $this->getJson('/api/v1/admin/returns?status=approved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', ReturnRequest::STATUS_APPROVED);
    }

    public function test_staff_can_show_return_request(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $returnRequest = ReturnRequest::query()->create([
            'email' => 'customer@example.com',
            'shopify_order_name' => '#1001',
            'reason' => 'damaged',
            'status' => ReturnRequest::STATUS_REQUESTED,
            'items' => [
                ['title' => 'Bath Bomb', 'quantity' => 1, 'unit_price' => 10],
            ],
        ]);

        $this->getJson("/api/v1/admin/returns/{$returnRequest->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $returnRequest->id)
            ->assertJsonPath('data.reason', 'damaged');
    }

    public function test_return_workflow_transitions(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $returnRequest = ReturnRequest::query()->create([
            'email' => 'customer@example.com',
            'shopify_order_name' => '#1001',
            'reason' => 'damaged',
            'status' => ReturnRequest::STATUS_REQUESTED,
        ]);

        $this->postJson("/api/v1/admin/returns/{$returnRequest->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', ReturnRequest::STATUS_APPROVED);

        $this->postJson("/api/v1/admin/returns/{$returnRequest->id}/received")
            ->assertOk()
            ->assertJsonPath('data.status', ReturnRequest::STATUS_RECEIVED);

        $this->postJson("/api/v1/admin/returns/{$returnRequest->id}/refunded")
            ->assertOk()
            ->assertJsonPath('data.status', ReturnRequest::STATUS_REFUNDED);
    }

    public function test_reject_return_requires_requested_status(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $returnRequest = ReturnRequest::query()->create([
            'email' => 'customer@example.com',
            'shopify_order_name' => '#1001',
            'reason' => 'damaged',
            'status' => ReturnRequest::STATUS_APPROVED,
        ]);

        $this->postJson("/api/v1/admin/returns/{$returnRequest->id}/reject", [
            'notes' => 'Too late',
        ])->assertStatus(422);
    }

    public function test_staff_can_update_return_notes_only(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $returnRequest = ReturnRequest::query()->create([
            'email' => 'customer@example.com',
            'shopify_order_name' => '#1001',
            'reason' => 'damaged',
            'status' => ReturnRequest::STATUS_REQUESTED,
        ]);

        $this->patchJson("/api/v1/admin/returns/{$returnRequest->id}", [
            'notes' => 'Customer called support',
        ])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Customer called support')
            ->assertJsonPath('data.status', ReturnRequest::STATUS_REQUESTED);
    }

    public function test_client_cannot_access_admin_returns(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Client]));

        $this->getJson('/api/v1/admin/returns')
            ->assertForbidden();
    }
}
