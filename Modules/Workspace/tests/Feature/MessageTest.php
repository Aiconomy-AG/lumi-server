<?php

namespace Modules\Workspace\Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Workspace\Events\MessageReactionUpdated;
use Modules\Workspace\Events\MessageSent;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Message;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageTest extends TestCase
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
    public function a_participant_can_send_a_message()
    {
        Event::fake([MessageSent::class]);

        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages", [
                'message' => 'Salut!',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.message_type', 'text')
            ->assertJsonPath('data.message', 'Salut!')
            ->assertJsonPath('data.sender_id', $user->id)
            ->assertJsonPath('data.conversation_id', $conversation->id);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'message' => 'Salut!',
        ]);

        Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->message->conversation_id === $conversation->id
                && $event->message->sender_id === $user->id
                && $event->message->message === 'Salut!'
        );
    }

    #[Test]
    public function sender_id_from_request_body_is_ignored()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages", [
                'message' => 'Impersonation attempt',
                'sender_id' => $other->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.sender_id', $user->id);
    }

    #[Test]
    public function a_participant_can_list_messages_in_chronological_order()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);

        $first = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $other->id,
        ]);
        $second = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/workspace/conversations/{$conversation->id}/messages");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id);
    }

    #[Test]
    public function message_listing_returns_the_latest_window_in_chronological_order()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);

        $messages = Message::factory()
            ->count(60)
            ->sequence(fn ($sequence) => [
                'conversation_id' => $conversation->id,
                'sender_id' => $sequence->index % 2 === 0 ? $user->id : $other->id,
                'created_at' => now()->addSeconds($sequence->index),
                'updated_at' => now()->addSeconds($sequence->index),
            ])
            ->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/workspace/conversations/{$conversation->id}/messages");

        $response->assertStatus(200)
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('data.0.id', $messages[10]->id)
            ->assertJsonPath('data.49.id', $messages[59]->id);
    }

    #[Test]
    public function a_non_participant_is_forbidden()
    {
        $participantOne = User::factory()->create();
        $participantTwo = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = $this->conversationWith($participantOne, $participantTwo);

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/v1/workspace/conversations/{$conversation->id}/messages")
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages", [
                'message' => 'Should not land',
            ])
            ->assertStatus(403);

        $this->assertDatabaseCount('messages', 0);
    }

    #[Test]
    public function a_missing_conversation_returns_404()
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/workspace/conversations/999999/messages')
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    #[Test]
    public function an_empty_message_is_rejected_with_422()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages", [
                'message' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->assertDatabaseCount('messages', 0);
    }

    #[Test]
    public function a_participant_can_react_to_a_message(): void
    {
        Event::fake([MessageReactionUpdated::class]);

        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $other->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages/{$message->id}/reactions", [
                'emoji' => '👍',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $message->id)
            ->assertJsonPath('data.reactions.0.emoji', '👍')
            ->assertJsonPath('data.reactions.0.count', 1)
            ->assertJsonPath('data.reactions.0.user_ids.0', $user->id);

        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '👍',
        ]);

        Event::assertDispatched(MessageReactionUpdated::class, fn (MessageReactionUpdated $event) => $event->message->id === $message->id
                && $event->message->relationLoaded('reactions')
        );
    }

    #[Test]
    public function reacting_with_the_same_emoji_twice_is_idempotent(): void
    {
        Event::fake([MessageReactionUpdated::class]);

        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $other->id,
        ]);

        $payload = ['emoji' => '🔥'];

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages/{$message->id}/reactions", $payload)
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages/{$message->id}/reactions", $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.reactions.0.count', 1);

        $this->assertDatabaseCount('message_reactions', 1);
    }

    #[Test]
    public function a_participant_can_remove_their_reaction(): void
    {
        Event::fake([MessageReactionUpdated::class]);

        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $other->id,
        ]);

        $message->reactions()->create([
            'user_id' => $user->id,
            'emoji' => '❤️',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/workspace/conversations/{$conversation->id}/messages/{$message->id}/reactions", [
                'emoji' => '❤️',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.reactions', []);

        $this->assertDatabaseMissing('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => '❤️',
        ]);
    }

    #[Test]
    public function a_message_must_belong_to_the_route_conversation_to_be_reacted_to(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $firstConversation = $this->conversationWith($user, $other);
        $secondConversation = $this->conversationWith($user, $other);
        $message = Message::factory()->create([
            'conversation_id' => $secondConversation->id,
            'sender_id' => $other->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$firstConversation->id}/messages/{$message->id}/reactions", [
                'emoji' => '👍',
            ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->assertDatabaseCount('message_reactions', 0);
    }

    #[Test]
    public function an_empty_reaction_emoji_is_rejected_with_422(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->conversationWith($user, $other);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $other->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages/{$message->id}/reactions", [
                'emoji' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('emoji');

        $this->assertDatabaseCount('message_reactions', 0);
    }

    #[Test]
    public function a_guest_is_unauthenticated()
    {
        $this->getJson('/api/v1/workspace/conversations/1/messages')
            ->assertStatus(401);
    }
}
