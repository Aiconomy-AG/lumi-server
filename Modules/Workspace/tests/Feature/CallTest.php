<?php

namespace Modules\Workspace\Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Modules\Workspace\Contracts\MediaRoomTokenProvider;
use Modules\Workspace\Domain\Calls\CallStatus;
use Modules\Workspace\Domain\Calls\ParticipantStatus;
use Modules\Workspace\Events\CallAccepted;
use Modules\Workspace\Events\CallIncoming;
use Modules\Workspace\Events\CallRinging;
use Modules\Workspace\Events\CallUpdated;
use Modules\Workspace\Jobs\DispatchCallRingJob;
use Modules\Workspace\Jobs\ExpireUnansweredCallJob;
use Modules\Workspace\Models\Call;
use Modules\Workspace\Models\CallParticipant;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Services\CallService;
use Modules\Workspace\Services\CallWebhookService;
use Modules\Workspace\Services\LiveKitService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('voip.enabled', true);
        config()->set('voip.livekit.url', 'wss://test.livekit.cloud');
        config()->set('voip.livekit.api_key', 'test-key');
        config()->set('voip.livekit.api_secret', str_repeat('s', 64));

        $this->mock(LiveKitService::class, function ($mock): void {
            $mock->shouldReceive('createRoom')->andReturnNull();
        });

        $this->mock(MediaRoomTokenProvider::class, function ($mock): void {
            $mock->shouldReceive('connectionFor')->andReturn([
                'url' => 'wss://test.livekit.cloud',
                'token' => 'fake-token',
            ]);
        });

        Queue::fake();
        Event::fake([CallRinging::class, CallIncoming::class, CallUpdated::class, CallAccepted::class]);
    }

    private function staffUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => UserRole::Employee], $attributes));
    }

    private function directConversation(User $caller, User $callee): Conversation
    {
        $conversation = Conversation::query()->create([
            'type' => 'direct',
            'created_by' => $caller->id,
        ]);
        $conversation->participants()->attach([$caller->id, $callee->id]);

        return $conversation;
    }

    #[Test]
    public function staff_can_start_a_call_via_primary_api(): void
    {
        if (! class_exists(JWT::class)) {
            $this->markTestSkipped('firebase/php-jwt is not installed.');
        }

        $this->app->forgetInstance(MediaRoomTokenProvider::class);
        $this->app->instance(MediaRoomTokenProvider::class, app(\Modules\Workspace\Infrastructure\LiveKitMediaRoomTokenProvider::class));

        $caller = $this->staffUser();
        $callee = $this->staffUser();

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$callee->id],
                'client_instance_id' => 'web-a',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.mode', '1v1')
            ->assertJsonPath('data.type', 'audio')
            ->assertJsonPath('data.connection.url', 'wss://test.livekit.cloud');

        $callId = $response->json('data.id');
        $this->assertStringStartsWith('call_', $response->json('data.room_name'));

        $claims = JWT::decode(
            $response->json('data.connection.token'),
            new Key(str_repeat('s', 64), 'HS256'),
        );
        $this->assertSame('call_'.$callId, $claims->video->room);
        $this->assertSame(['microphone'], $claims->video->canPublishSources);

        Queue::assertPushed(DispatchCallRingJob::class);
        Queue::assertPushed(ExpireUnansweredCallJob::class);
    }

    #[Test]
    public function video_call_token_allows_publish_and_subscribe(): void
    {
        if (! class_exists(JWT::class)) {
            $this->markTestSkipped('firebase/php-jwt is not installed.');
        }

        $this->app->forgetInstance(MediaRoomTokenProvider::class);
        $this->app->instance(MediaRoomTokenProvider::class, app(\Modules\Workspace\Infrastructure\LiveKitMediaRoomTokenProvider::class));

        $caller = $this->staffUser();
        $callee = $this->staffUser();

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$callee->id],
                'type' => 'video',
                'client_instance_id' => 'web-a',
            ]);

        $response->assertCreated()->assertJsonPath('data.type', 'video');

        $claims = JWT::decode(
            $response->json('data.connection.token'),
            new Key(str_repeat('s', 64), 'HS256'),
        );
        $this->assertTrue($claims->video->roomJoin);
        $this->assertTrue($claims->video->canPublish);
        $this->assertTrue($claims->video->canSubscribe);
    }

    #[Test]
    public function accept_broadcasts_connection_to_caller(): void
    {
        Event::fake([CallAccepted::class, CallUpdated::class]);

        $caller = $this->staffUser();
        $callee = $this->staffUser();

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$callee->id],
                'type' => 'video',
                'client_instance_id' => 'android-caller',
            ])->json('data.id');

        $this->actingAs($callee, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/accept", [
                'client_instance_id' => 'android-callee',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.connection.url', 'wss://test.livekit.cloud');

        Event::assertDispatched(CallAccepted::class, function (CallAccepted $event) use ($caller): bool {
            return $event->recipientUserId === (int) $caller->id
                && $event->connection !== null
                && ($event->broadcastWith()['connection']['url'] ?? null) === 'wss://test.livekit.cloud';
        });
    }

    #[Test]
    public function workspace_conversation_route_still_starts_calls(): void
    {
        $caller = $this->staffUser();
        $callee = $this->staffUser();
        $conversation = $this->directConversation($caller, $callee);

        $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/calls", [
                'client_instance_id' => 'web-a',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.conversation_id', $conversation->id);
    }

    #[Test]
    public function first_recipient_device_to_accept_wins_for_1v1(): void
    {
        $caller = $this->staffUser();
        $callee = $this->staffUser();

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$callee->id],
                'client_instance_id' => 'web-caller',
            ])->json('data.id');

        $this->actingAs($callee, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/accept", [
                'client_instance_id' => 'android-one',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.answered_client_instance_id', 'android-one');

        $this->actingAs($callee, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/accept", [
                'client_instance_id' => 'web-two',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'ANSWERED_ELSEWHERE');
    }

    #[Test]
    public function group_call_stays_ringing_when_one_callee_declines_and_another_accepts(): void
    {
        $caller = $this->staffUser();
        $calleeOne = $this->staffUser();
        $calleeTwo = $this->staffUser();

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$calleeOne->id, $calleeTwo->id],
                'mode' => 'group',
                'client_instance_id' => 'web-caller',
            ])->json('data.id');

        $this->actingAs($calleeOne, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/decline")
            ->assertOk();

        $this->assertDatabaseHas('calls', ['id' => $callId, 'status' => CallStatus::Ringing->value]);

        $this->actingAs($calleeTwo, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/accept", [
                'client_instance_id' => 'android-two',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    #[Test]
    public function leave_ends_call_when_last_active_participant_leaves(): void
    {
        $caller = $this->staffUser();
        $callee = $this->staffUser();

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$callee->id],
                'client_instance_id' => 'web-caller',
            ])->json('data.id');

        $this->actingAs($callee, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/accept", ['client_instance_id' => 'android'])
            ->assertOk();

        $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/leave")
            ->assertOk();

        $this->actingAs($callee, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/leave")
            ->assertOk()
            ->assertJsonPath('data.status', 'ended');
    }

    #[Test]
    public function end_force_ends_active_call_for_all_participants(): void
    {
        $caller = $this->staffUser();
        $callee = $this->staffUser();

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$callee->id],
                'client_instance_id' => 'web-caller',
            ])->json('data.id');

        $this->actingAs($callee, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/accept", ['client_instance_id' => 'android'])
            ->assertOk();

        $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/end")
            ->assertOk()
            ->assertJsonPath('data.status', 'ended');
    }

    #[Test]
    public function group_call_can_invite_additional_participants(): void
    {
        $caller = $this->staffUser();
        $callee = $this->staffUser();
        $invitee = $this->staffUser();

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$callee->id],
                'mode' => 'group',
                'client_instance_id' => 'web-caller',
            ])->json('data.id');

        $this->actingAs($callee, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/accept", ['client_instance_id' => 'android'])
            ->assertOk();

        $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/invite", [
                'user_ids' => [$invitee->id],
            ])
            ->assertOk()
            ->assertJsonCount(3, 'data.participants');

        Queue::assertPushed(DispatchCallRingJob::class, 2);
    }

    #[Test]
    public function call_history_returns_terminal_calls(): void
    {
        $caller = $this->staffUser();
        $callee = $this->staffUser();

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$callee->id],
                'client_instance_id' => 'web-caller',
            ])->json('data.id');

        $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/cancel")
            ->assertOk();

        $this->actingAs($caller, 'sanctum')
            ->getJson('/api/v1/calls/history')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $callId);
    }

    #[Test]
    public function unanswered_call_expires_as_missed(): void
    {
        $caller = $this->staffUser();
        $callee = $this->staffUser();

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$callee->id],
                'client_instance_id' => 'web-caller',
            ])->json('data.id');

        (new ExpireUnansweredCallJob($callId))->handle(app(CallService::class));

        $this->assertDatabaseHas('calls', ['id' => $callId, 'status' => 'missed']);
    }

    #[Test]
    public function webhook_participant_joined_sets_joined_at(): void
    {
        if (! class_exists(\Livekit\WebhookEvent::class)) {
            $this->markTestSkipped('LiveKit PHP SDK is not installed.');
        }

        $caller = $this->staffUser();
        $callee = $this->staffUser();

        $call = Call::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'initiated_by_user_id' => $caller->id,
            'caller_name' => $caller->name,
            'destination_type' => Call::DESTINATION_WORKSPACE_USER,
            'mode' => '1v1',
            'type' => 'audio',
            'media_type' => 'audio',
            'status' => CallStatus::Ringing,
            'room_name' => 'call_test_room',
        ]);

        $participant = CallParticipant::query()->create([
            'call_id' => $call->id,
            'user_id' => $callee->id,
            'role' => 'callee',
            'status' => ParticipantStatus::Ringing,
        ]);

        $identity = 'user:'.$callee->id.':client:android';
        $event = new \Livekit\WebhookEvent([
            'event' => 'participant_joined',
            'id' => 'evt-1',
            'room' => new \Livekit\Room(['name' => 'call_test_room']),
            'participant' => new \Livekit\ParticipantInfo(['identity' => $identity]),
        ]);

        $service = app(CallWebhookService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('participantJoined');
        $method->setAccessible(true);
        $method->invoke($service, $event);

        $participant->refresh();
        $this->assertNotNull($participant->joined_at);
        $this->assertSame(ParticipantStatus::Joined, $participant->status);
    }

    #[Test]
    public function terminal_call_is_logged_in_conversation_messages(): void
    {
        $caller = $this->staffUser();
        $callee = $this->staffUser();
        $conversation = $this->directConversation($caller, $callee);

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/calls", [
                'client_instance_id' => 'web-caller',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->actingAs($caller, 'sanctum')
            ->getJson("/api/v1/workspace/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.message_type', 'call')
            ->assertJsonPath('data.0.call.id', $callId)
            ->assertJsonPath('data.0.call.status', 'cancelled')
            ->assertJsonPath('data.0.message', 'Cancelled Call');
    }
}
