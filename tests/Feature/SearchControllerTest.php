<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Sales\Models\Customer;
use Modules\Sales\Models\Order;
use Modules\Sales\Models\Product;
use Modules\Sales\Models\ReturnRequest;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Project;
use Modules\Workspace\Models\Task;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_search_returns_matching_entities_including_users(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $targetUser = User::factory()->create([
            'name' => 'Sleepy Staffmate',
            'email' => 'sleepy-staff@example.com',
        ]);

        $project = Project::create([
            'name' => 'Sleepy Launch',
            'description' => 'Q3 marketing rollout',
            'status' => 'in_progress',
        ]);

        $task = Task::create([
            'title' => 'Review Sleepy launch',
            'description' => 'Check packaging',
            'status' => 'to_do',
            'project_id' => $project->id,
        ]);

        Product::create([
            'name' => 'Sleepy Soap',
            'sku' => 'SLP-100',
            'description' => 'Relaxing soap',
            'price' => 19.99,
        ]);

        $response = $this->getJson('/api/v1/search?q=sleepy');

        $response->assertOk()
            ->assertJsonPath('data.query', 'sleepy')
            ->assertJsonStructure([
                'data' => [
                    'query',
                    'results' => [
                        '*' => ['type', 'module', 'id', 'title', 'url'],
                    ],
                    'meta' => ['total', 'per_type'],
                ],
            ]);

        $types = collect($response->json('data.results'))->pluck('type')->all();

        $this->assertContains('task', $types);
        $this->assertContains('project', $types);
        $this->assertContains('product', $types);
        $this->assertContains('user', $types);
        $this->assertContains($task->id, collect($response->json('data.results'))->where('type', 'task')->pluck('id'));
        $this->assertContains($targetUser->id, collect($response->json('data.results'))->where('type', 'user')->pluck('id'));
    }

    public function test_client_cannot_access_search(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Client]));

        $this->getJson('/api/v1/search?q=test')
            ->assertForbidden();
    }

    public function test_search_requires_minimum_query_length(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/search?q=a')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    public function test_search_can_filter_by_types(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Task::create([
            'title' => 'Sleepy task only',
            'status' => 'to_do',
        ]);

        Product::create([
            'name' => 'Sleepy product only',
            'sku' => 'SLP-200',
            'price' => 9.99,
        ]);

        $response = $this->getJson('/api/v1/search?q=sleepy&types[]=task');

        $response->assertOk();

        $types = collect($response->json('data.results'))->pluck('type')->unique()->values()->all();

        $this->assertSame(['task'], $types);
    }

    public function test_search_excludes_completed_tasks_by_default(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Task::create([
            'title' => 'Sleepy completed task',
            'status' => 'complete',
        ]);

        Task::create([
            'title' => 'Sleepy active task',
            'status' => 'in_progress',
        ]);

        $response = $this->getJson('/api/v1/search?q=sleepy&types[]=task');

        $response->assertOk();

        $titles = collect($response->json('data.results'))->pluck('title')->all();

        $this->assertContains('Sleepy active task', $titles);
        $this->assertNotContains('Sleepy completed task', $titles);
    }

    public function test_search_includes_completed_tasks_when_requested(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Task::create([
            'title' => 'Sleepy completed task',
            'status' => 'complete',
        ]);

        $response = $this->getJson('/api/v1/search?q=sleepy&types[]=task&include_completed=1');

        $response->assertOk();

        $titles = collect($response->json('data.results'))->pluck('title')->all();

        $this->assertContains('Sleepy completed task', $titles);
    }

    public function test_search_returns_orders_and_returns(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $customer = Customer::create([
            'username' => 'sleepy_customer',
            'email' => 'sleepy@example.com',
            'shopify_customer_id' => 'cus_sleepy',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'shopify_order_name' => '#SLEEPY-1001',
            'status' => 'paid',
            'subtotal' => 10,
            'shipping_cost' => 0,
            'total_amount' => 10,
            'payment_status' => 'unshipped',
        ]);

        $return = ReturnRequest::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'shopify_order_name' => '#SLEEPY-1001',
            'email' => 'sleepy@example.com',
            'items' => [],
            'status' => ReturnRequest::STATUS_REQUESTED,
        ]);

        $response = $this->getJson('/api/v1/search?q=sleepy');

        $response->assertOk();

        $results = collect($response->json('data.results'));

        $this->assertTrue($results->where('type', 'order')->contains('id', $order->id));
        $this->assertTrue($results->where('type', 'return')->contains('id', $return->id));
    }

    public function test_search_never_returns_conversations(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $other = User::factory()->create(['name' => 'Sleepy Teammate']);

        $conversation = Conversation::factory()->create([
            'type' => 'group',
            'name' => 'Sleepy planning',
            'created_by' => $user->id,
        ]);
        $conversation->participants()->attach([$user->id, $other->id]);

        $response = $this->getJson('/api/v1/search?q=sleepy');

        $response->assertOk();

        $this->assertFalse(
            collect($response->json('data.results'))
                ->pluck('type')
                ->contains('conversation')
        );
    }
}
