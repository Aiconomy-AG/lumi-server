<?php

namespace Modules\Workspace\Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Workspace\Models\Conversation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConversationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(function (string $modelName) {
            if ($modelName === 'App\\Models\\User') {
                return 'Database\\Factories\\UserFactory';
            }

            return 'Modules\\Workspace\\Database\\Factories\\'.class_basename($modelName).'Factory';
        });
    }

    private function conversationWith(User ...$users): Conversation
    {
        $conversation = Conversation::factory()->create([
            'created_by' => $users[0]->id,
        ]);
        $conversation->participants()->attach(collect($users)->pluck('id'));

        return $conversation;
    }

    #[Test]
    public function non_participant_cannot_view_conversation(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Employee]);
        $outsider = User::factory()->create(['role' => UserRole::Employee]);
        $conversation = $this->conversationWith($participant);

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/workspace/conversations/{$conversation->id}")
            ->assertForbidden();
    }

    #[Test]
    public function participant_can_view_conversation(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Employee]);
        $conversation = $this->conversationWith($participant);

        Sanctum::actingAs($participant);

        $this->getJson("/api/v1/workspace/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $conversation->id);
    }

    #[Test]
    public function client_participant_can_view_conversation(): void
    {
        $client = User::factory()->create(['role' => UserRole::Client]);
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $conversation = $this->conversationWith($client, $employee);

        Sanctum::actingAs($client);

        $this->getJson("/api/v1/workspace/conversations/{$conversation->id}")
            ->assertOk();
    }

    #[Test]
    public function non_participant_cannot_update_group_conversation(): void
    {
        $participant = User::factory()->create(['role' => UserRole::Employee]);
        $outsider = User::factory()->create(['role' => UserRole::Employee]);
        $conversation = Conversation::factory()->create([
            'type' => 'group',
            'name' => 'Team chat',
            'created_by' => $participant->id,
        ]);
        $conversation->participants()->attach($participant->id);

        Sanctum::actingAs($outsider);

        $this->putJson("/api/v1/workspace/conversations/{$conversation->id}", [
            'name' => 'Renamed',
        ])->assertForbidden();
    }
}
