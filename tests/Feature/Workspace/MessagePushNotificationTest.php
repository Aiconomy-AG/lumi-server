<?php

namespace Tests\Feature\Workspace;

use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Modules\Workspace\Models\Conversation;
use Tests\TestCase;

class MessagePushNotificationTest extends TestCase
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

    public function test_sending_message_dispatches_push_to_other_participants(): void
    {
        Queue::fake();

        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'created_by' => $sender->id,
        ]);
        $conversation->participants()->attach([$sender->id, $recipient->id]);

        Sanctum::actingAs($sender);

        $response = $this->postJson("/api/v1/workspace/conversations/{$conversation->id}/messages", [
            'message' => 'Hello from mobile.',
        ])->assertCreated();

        $messageId = $response->json('data.id');

        Queue::assertPushed(
            SendPushNotificationJob::class,
            fn (SendPushNotificationJob $job): bool => $job->userId === $recipient->id
                && $job->title === 'New message'
                && $job->body === 'You received a new message'
                && $job->data === [
                    'type' => 'chat_message_received',
                    'conversation_id' => (string) $conversation->id,
                    'message_id' => (string) $messageId,
                ]
        );

        Queue::assertNotPushed(
            SendPushNotificationJob::class,
            fn (SendPushNotificationJob $job): bool => $job->userId === $sender->id
        );
    }
}
