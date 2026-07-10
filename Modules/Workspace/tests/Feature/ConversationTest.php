<?php

namespace Modules\Workspace\Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Message;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConversationTest extends TestCase
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
    public function conversation_index_includes_last_message_preview(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $other->id,
            'message' => 'See you at 3pm',
            'created_at' => now()->subHour(),
        ]);

        $latest = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'message' => 'Sounds good!',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/workspace/conversations');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversation->id)
            ->assertJsonPath('data.0.last_message.message', 'Sounds good!')
            ->assertJsonPath('data.0.last_message.sender_id', $user->id)
            ->assertJsonPath('data.0.last_message.sent_at', $latest->created_at?->toISOString());
    }

    #[Test]
    public function conversation_without_messages_has_null_last_message(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/workspace/conversations');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversation->id)
            ->assertJsonMissingPath('data.0.last_message');
    }
}
