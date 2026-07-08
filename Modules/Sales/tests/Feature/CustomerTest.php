<?php

namespace Modules\Sales\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Factories\Factory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Modules\Sales\Models\Customer;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(function (string $modelName) {
            if ($modelName === 'App\\Models\\User') {
                return 'Database\\Factories\\UserFactory';
            }
            return 'Modules\\Sales\\Database\\Factories\\' . class_basename($modelName) . 'Factory';
        });
    }

    #[Test]
    public function it_can_retrieve_the_logged_in_customer_profile_via_me_endpoint()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['email' => $user->email]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shop/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $user->email);
    }

    #[Test]
    public function a_customer_can_view_their_own_profile_by_id()
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['email' => $user->email]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/shop/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $customer->id);
    }

    #[Test]
    public function a_customer_cannot_view_another_customers_profile()
    {
        $userOne = User::factory()->create();
        Customer::factory()->create(['email' => $userOne->email]);

        $userTwo = User::factory()->create();
        $customerTwo = Customer::factory()->create(['email' => $userTwo->email]);

        $response = $this->actingAs($userOne, 'sanctum')
            ->getJson("/api/v1/shop/customers/{$customerTwo->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_returns_404_if_the_customer_profile_does_not_exist()
    {
        $user = User::factory()->create();
        Customer::factory()->create(['email' => $user->email]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shop/customers/99999');

        $response->assertStatus(403);
    }
}
