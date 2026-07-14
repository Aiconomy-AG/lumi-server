<?php

namespace Modules\Workspace\Tests\Feature;

use App\Events\NotificationDelivered;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Modules\Workspace\Events\CallRinging;
use Modules\Workspace\Events\CallUpdated;
use Modules\Workspace\Jobs\ExpireUnansweredCallJob;
use Modules\Workspace\Jobs\SendCallPushJob;
use Modules\Workspace\Models\Conversation;
use Modules\Workspace\Services\CallService;
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
        Queue::fake();
        Event::fake([CallRinging::class, CallUpdated::class, NotificationDelivered::class]);
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
    public function caller_must_have_an_international_phone_number(): void
    {
        $caller = User::factory()->create(['phone_number' => '']);
        $callee = User::factory()->create();
        $conversation = $this->directConversation($caller, $callee);

        $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/calls", [
                'client_instance_id' => 'web-a',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PHONE_NUMBER_REQUIRED');
    }

    #[Test]
    public function participant_can_start_an_audio_call_and_receives_a_scoped_connection(): void
    {
        $caller = User::factory()->create(['phone_number' => '+40722123456']);
        $callee = User::factory()->create();
        $conversation = $this->directConversation($caller, $callee);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/calls", [
                'client_instance_id' => 'web-a',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.media_type', 'audio')
            ->assertJsonPath('data.caller.phone_number', '+40722123456')
            ->assertJsonPath('data.connection.url', 'wss://test.livekit.cloud');

        $this->assertNotEmpty($response->json('data.connection.token'));
        $claims = JWT::decode(
            $response->json('data.connection.token'),
            new Key(str_repeat('s', 64), 'HS256'),
        );
        $this->assertSame('lumi-call-'.$response->json('data.id'), $claims->video->room);
        $this->assertSame(['microphone'], $claims->video->canPublishSources);
        $this->assertFalse($claims->video->canPublishData);
        $this->assertDatabaseHas('calls', [
            'id' => $response->json('data.id'),
            'caller_phone_number' => '+40722123456',
            'status' => 'ringing',
        ]);
        Event::assertDispatched(CallRinging::class);
        Queue::assertPushed(SendCallPushJob::class);
        Queue::assertPushed(ExpireUnansweredCallJob::class);
    }

    #[Test]
    public function first_recipient_device_to_accept_wins(): void
    {
        $caller = User::factory()->create(['phone_number' => '+40722123456']);
        $callee = User::factory()->create();
        $conversation = $this->directConversation($caller, $callee);

        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/calls", [
                'client_instance_id' => 'web-caller',
            ])->json('data.id');

        $this->actingAs($callee, 'sanctum')
            ->postJson("/api/v1/workspace/calls/{$callId}/accept", [
                'client_instance_id' => 'android-one',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.answered_client_instance_id', 'android-one');

        $this->actingAs($callee, 'sanctum')
            ->postJson("/api/v1/workspace/calls/{$callId}/accept", [
                'client_instance_id' => 'web-two',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'ANSWERED_ELSEWHERE');
    }

    #[Test]
    public function second_call_is_rejected_while_a_participant_is_busy(): void
    {
        $caller = User::factory()->create(['phone_number' => '+40722123456']);
        $callee = User::factory()->create();
        $third = User::factory()->create(['phone_number' => '+40733123456']);
        $first = $this->directConversation($caller, $callee);
        $second = $this->directConversation($third, $callee);

        $this->actingAs($caller, 'sanctum')->postJson("/api/v1/workspace/conversations/{$first->id}/calls", [
            'client_instance_id' => 'first',
        ])->assertCreated();

        $this->actingAs($third, 'sanctum')->postJson("/api/v1/workspace/conversations/{$second->id}/calls", [
            'client_instance_id' => 'second',
        ])->assertStatus(409)->assertJsonPath('code', 'USER_BUSY');
    }

    #[Test]
    public function profile_phone_update_normalizes_supported_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/auth/phone', ['phone_number' => '+40 722-123-456'])
            ->assertOk()
            ->assertJsonPath('phone_number', '+40722123456');

        $this->assertSame('+40722123456', $user->fresh()->phone_number);
    }

    #[Test]
    public function unanswered_call_expires_and_creates_a_missed_call_notification(): void
    {
        $caller = User::factory()->create(['phone_number' => '+40722123456']);
        $callee = User::factory()->create();
        $conversation = $this->directConversation($caller, $callee);
        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/calls", [
                'client_instance_id' => 'web-caller',
            ])->json('data.id');

        (new ExpireUnansweredCallJob($callId))->handle(app(CallService::class));

        $this->assertDatabaseHas('calls', ['id' => $callId, 'status' => 'missed']);
        $this->assertDatabaseHas('notification_events', [
            'type' => 'call_missed',
            'source' => 'call',
            'conversation_id' => $conversation->id,
        ]);
        $this->assertDatabaseHas('notification_deliveries', ['recipient_user_id' => $callee->id]);
    }

    #[Test]
    public function terminal_actions_are_idempotent(): void
    {
        $caller = User::factory()->create(['phone_number' => '+40722123456']);
        $callee = User::factory()->create();
        $conversation = $this->directConversation($caller, $callee);
        $callId = $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/workspace/conversations/{$conversation->id}/calls", [
                'client_instance_id' => 'web-caller',
            ])->json('data.id');

        $this->actingAs($caller, 'sanctum')->postJson("/api/v1/workspace/calls/{$callId}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->actingAs($caller, 'sanctum')->postJson("/api/v1/workspace/calls/{$callId}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
    }
}
