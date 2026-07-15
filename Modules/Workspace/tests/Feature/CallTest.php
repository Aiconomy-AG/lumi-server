<?php

namespace Modules\Workspace\Tests\Feature;

use App\Enums\UserRole;
use App\Events\NotificationDelivered;
use App\Jobs\SendPushNotificationJob;
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
use Modules\Workspace\Models\CallEvent;
use Modules\Workspace\Models\CallParticipant;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Models\Message;
use Modules\Workspace\Models\NotificationEvent;
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
            $mock->shouldReceive('deleteRoom')->andReturnNull();
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

    private function groupConversation(array $users): Conversation
    {
        $conversation = Conversation::query()->create([
            'type' => 'group',
            'created_by' => $users[0]->id,
        ]);
        $conversation->participants()->attach(collect($users)->pluck('id')->all());

        return $conversation;
    }

    private function signedLiveKitWebhookPayload(array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $now = time();
        $token = JWT::encode([
            'exp' => $now + 60,
            'nbf' => $now - 5,
            'iat' => $now,
            'iss' => config('voip.livekit.api_key'),
            'sha256' => base64_encode(hash('sha256', $body, true)),
        ], config('voip.livekit.api_secret'), 'HS256');

        return [$body, $token];
    }

    private function invokeWebhookParticipantLeft(string $callId, int $userId, string $clientInstanceId): void
    {
        $event = new \Livekit\WebhookEvent([
            'event' => 'participant_left',
            'id' => 'evt-left-'.$userId,
            'room' => new \Livekit\Room(['name' => 'call_'.$callId]),
            'participant' => new \Livekit\ParticipantInfo([
                'identity' => 'user:'.$userId.':client:'.$clientInstanceId,
            ]),
        ]);

        $service = app(CallWebhookService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('participantLeft');
        $method->setAccessible(true);
        $method->invoke($service, $event);
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
        $this->assertSame(['microphone', 'screen_share'], $claims->video->canPublishSources);

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
        $this->assertSame(['microphone', 'camera', 'screen_share'], $claims->video->canPublishSources);
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
    public function workspace_conversation_route_starts_video_calls(): void
    {
        $caller = $this->staffUser();
        $callee = $this->staffUser();
        $conversation = $this->directConversation($caller, $callee);

        $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/calls", [
                'client_instance_id' => 'web-a',
                'type' => 'video',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.type', 'video')
            ->assertJsonPath('data.conversation_id', $conversation->id);
    }

    #[Test]
    public function callee_busy_conflict_creates_no_call_and_notifies_busy_user(): void
    {
        Event::fake([CallRinging::class, CallIncoming::class, CallUpdated::class, CallAccepted::class, NotificationDelivered::class]);

        $caller = $this->staffUser();
        $busyCallee = $this->staffUser();
        $other = $this->staffUser();
        $conversation = $this->directConversation($caller, $busyCallee);

        $this->actingAs($busyCallee, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$other->id],
                'client_instance_id' => 'busy-device',
            ])
            ->assertCreated();

        $beforeCount = Call::query()->count();

        $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/calls", [
                'client_instance_id' => 'web-a',
                'type' => 'video',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'USER_BUSY');

        $this->assertSame($beforeCount, Call::query()->count());

        $event = NotificationEvent::query()->where('type', 'call_attempted_while_busy')->sole();
        $this->assertSame((int) $caller->id, (int) $event->actor_user_id);
        $this->assertSame((int) $conversation->id, (int) $event->conversation_id);
        $this->assertSame('video', $event->payload['media_type']);

        Event::assertDispatched(NotificationDelivered::class);
        Queue::assertPushed(SendPushNotificationJob::class, function (SendPushNotificationJob $job) use ($busyCallee): bool {
            return $job->userId === (int) $busyCallee->id
                && ($job->data['type'] ?? null) === 'call_attempted_while_busy'
                && ($job->data['media_type'] ?? null) === 'video';
        });
    }

    #[Test]
    public function call_presence_marks_available_users_busy_and_restores_after_decline(): void
    {
        $caller = $this->staffUser(['status' => 'available']);
        $callee = $this->staffUser(['status' => 'available']);

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$callee->id],
                'client_instance_id' => 'web-caller',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame('busy', $caller->fresh()->status);
        $this->assertSame('busy', $callee->fresh()->status);

        $this->actingAs($callee, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');

        $this->assertSame('available', $caller->fresh()->status);
        $this->assertSame('available', $callee->fresh()->status);
    }

    #[Test]
    public function manual_busy_and_away_users_are_not_restored_to_available(): void
    {
        $caller = $this->staffUser(['status' => 'busy']);
        $callee = $this->staffUser(['status' => 'away']);

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$callee->id],
                'client_instance_id' => 'web-caller',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($callee, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/decline")
            ->assertOk();

        $this->assertSame('busy', $caller->fresh()->status);
        $this->assertSame('away', $callee->fresh()->status);
    }

    #[Test]
    public function group_calls_reject_end_and_finish_when_last_participant_leaves(): void
    {
        $caller = $this->staffUser();
        $callee = $this->staffUser();

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
            ->postJson("/api/v1/calls/{$callId}/end")
            ->assertStatus(422)
            ->assertJsonPath('code', 'GROUP_CALL_LEAVE_REQUIRED');

        $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/leave")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->actingAs($callee, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/leave")
            ->assertOk()
            ->assertJsonPath('data.status', 'ended');
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
    public function group_call_start_rejects_participants_above_livekit_group_limit(): void
    {
        config()->set('voip.livekit.max_participants_group', 2);

        $caller = $this->staffUser();
        $calleeOne = $this->staffUser();
        $calleeTwo = $this->staffUser();

        $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$calleeOne->id, $calleeTwo->id],
                'mode' => 'group',
                'client_instance_id' => 'web-caller',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'CALL_PARTICIPANT_LIMIT_EXCEEDED');
    }

    #[Test]
    public function group_call_invite_rejects_participants_above_livekit_group_limit(): void
    {
        config()->set('voip.livekit.max_participants_group', 3);

        $caller = $this->staffUser();
        $callee = $this->staffUser();
        $inviteeOne = $this->staffUser();
        $inviteeTwo = $this->staffUser();

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$callee->id],
                'mode' => 'group',
                'client_instance_id' => 'web-caller',
            ])->json('data.id');

        $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/invite", [
                'user_ids' => [$inviteeOne->id, $inviteeTwo->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'CALL_PARTICIPANT_LIMIT_EXCEEDED');
    }

    #[Test]
    public function group_call_invite_within_livekit_group_limit_still_succeeds(): void
    {
        config()->set('voip.livekit.max_participants_group', 3);

        $caller = $this->staffUser();
        $callee = $this->staffUser();
        $invitee = $this->staffUser();

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls', [
                'callee_ids' => [$callee->id],
                'mode' => 'group',
                'client_instance_id' => 'web-caller',
            ])->json('data.id');

        $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/invite", [
                'user_ids' => [$invitee->id],
            ])
            ->assertOk()
            ->assertJsonCount(3, 'data.participants');
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
    public function livekit_webhook_rejects_missing_authorization_header(): void
    {
        $this->call(
            'POST',
            '/api/v1/webhooks/livekit',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/webhook+json'],
            '{"event":"participant_joined"}',
        )->assertStatus(401);
    }

    #[Test]
    public function livekit_webhook_rejects_malformed_json(): void
    {
        $this->call(
            'POST',
            '/api/v1/webhooks/livekit',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/webhook+json'],
            '{"event":',
        )->assertStatus(400);
    }

    #[Test]
    public function livekit_webhook_accepts_unknown_room_without_logging_invalid_call_event(): void
    {
        if (! class_exists(\Livekit\WebhookEvent::class)) {
            $this->markTestSkipped('LiveKit PHP SDK is not installed.');
        }

        [$body, $token] = $this->signedLiveKitWebhookPayload([
            'event' => 'participant_joined',
            'id' => 'evt-unknown',
            'room' => ['name' => 'missing_room'],
        ]);

        $this->call(
            'POST',
            '/api/v1/webhooks/livekit',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/webhook+json',
                'HTTP_AUTHORIZATION' => $token,
            ],
            $body,
        )->assertOk();

        $this->assertSame(0, CallEvent::query()->count());
    }

    #[Test]
    public function group_webhook_participant_left_keeps_call_active_while_others_are_joined(): void
    {
        if (! class_exists(\Livekit\WebhookEvent::class)) {
            $this->markTestSkipped('LiveKit PHP SDK is not installed.');
        }

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
            ->postJson("/api/v1/calls/{$callId}/accept", ['client_instance_id' => 'android-one'])
            ->assertOk();
        $this->actingAs($calleeTwo, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/accept", ['client_instance_id' => 'android-two'])
            ->assertOk();

        $this->invokeWebhookParticipantLeft($callId, $calleeOne->id, 'android-one');

        $this->assertDatabaseHas('calls', ['id' => $callId, 'status' => CallStatus::Active->value]);
        $this->assertDatabaseHas('call_participants', [
            'call_id' => $callId,
            'user_id' => $calleeOne->id,
            'status' => ParticipantStatus::Left->value,
        ]);
    }

    #[Test]
    public function group_webhook_participant_left_ends_call_when_last_joined_participant_leaves(): void
    {
        if (! class_exists(\Livekit\WebhookEvent::class)) {
            $this->markTestSkipped('LiveKit PHP SDK is not installed.');
        }

        $caller = $this->staffUser();
        $callee = $this->staffUser();
        $conversation = $this->groupConversation([$caller, $callee]);

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/calls", [
                'client_instance_id' => 'web-caller',
            ])->json('data.id');

        $this->actingAs($callee, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/accept", ['client_instance_id' => 'android'])
            ->assertOk();

        $this->invokeWebhookParticipantLeft($callId, $callee->id, 'android');
        $this->invokeWebhookParticipantLeft($callId, $caller->id, 'web-caller');

        $this->assertDatabaseHas('calls', ['id' => $callId, 'status' => CallStatus::Ended->value]);
        $this->assertSame(1, Message::query()->where('call_id', $callId)->count());
    }

    #[Test]
    public function room_finished_ends_group_call_and_marks_joined_participants_left(): void
    {
        if (! class_exists(\Livekit\WebhookEvent::class)) {
            $this->markTestSkipped('LiveKit PHP SDK is not installed.');
        }

        $caller = $this->staffUser();
        $calleeOne = $this->staffUser();
        $calleeTwo = $this->staffUser();
        $conversation = $this->groupConversation([$caller, $calleeOne, $calleeTwo]);

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/calls", [
                'client_instance_id' => 'web-caller',
            ])->json('data.id');

        $this->actingAs($calleeOne, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/accept", ['client_instance_id' => 'android-one'])
            ->assertOk();
        $this->actingAs($calleeTwo, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/accept", ['client_instance_id' => 'android-two'])
            ->assertOk();

        $event = new \Livekit\WebhookEvent([
            'event' => 'room_finished',
            'id' => 'evt-room-finished',
            'room' => new \Livekit\Room(['name' => 'call_'.$callId]),
        ]);

        $service = app(CallWebhookService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('roomFinished');
        $method->setAccessible(true);
        $method->invoke($service, $event);

        $this->assertDatabaseHas('calls', ['id' => $callId, 'status' => CallStatus::Ended->value]);
        $this->assertSame(0, CallParticipant::query()
            ->where('call_id', $callId)
            ->where('status', ParticipantStatus::Joined->value)
            ->count());
        $this->assertSame(1, Message::query()->where('call_id', $callId)->count());
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
