<?php

namespace Modules\Workspace\Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Workspace\Domain\Calls\CallStatus;
use Modules\Workspace\Domain\Calls\CallType;
use Modules\Workspace\Domain\Calls\ParticipantStatus;
use Modules\Workspace\Events\CallAccepted;
use Modules\Workspace\Models\Call;
use Modules\Workspace\Models\CallParticipant;
use Modules\Workspace\Services\CallConnectionResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CallConnectionResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('voip.enabled', true);
        config()->set('voip.livekit.url', 'wss://test.livekit.cloud');
        config()->set('voip.livekit.api_key', 'test-key');
        config()->set('voip.livekit.api_secret', str_repeat('s', 64));
    }

    #[Test]
    public function it_includes_connection_for_active_caller_and_joined_callee(): void
    {
        $caller = User::factory()->create(['role' => UserRole::Employee]);
        $callee = User::factory()->create(['role' => UserRole::Employee]);

        $call = Call::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'initiated_by_user_id' => $caller->id,
            'caller_name' => $caller->name,
            'destination_type' => Call::DESTINATION_WORKSPACE_USER,
            'mode' => '1v1',
            'type' => CallType::Video,
            'media_type' => 'video',
            'status' => CallStatus::Active,
            'room_name' => 'call_test',
            'answered_client_instance_id' => 'android-callee',
        ]);

        CallParticipant::query()->create([
            'call_id' => $call->id,
            'user_id' => $caller->id,
            'role' => 'caller',
            'status' => ParticipantStatus::Joined,
            'client_instance_id' => 'android-caller',
        ]);
        CallParticipant::query()->create([
            'call_id' => $call->id,
            'user_id' => $callee->id,
            'role' => 'callee',
            'status' => ParticipantStatus::Joined,
            'client_instance_id' => 'android-callee',
        ]);

        $call->load('participants.user');
        $resolver = app(CallConnectionResolver::class);

        $callerConnection = $resolver->connectionForParticipant($call, $call->participants->firstWhere('role', 'caller'));
        $calleeConnection = $resolver->connectionForParticipant($call, $call->participants->firstWhere('role', 'callee'));

        $this->assertNotNull($callerConnection);
        $this->assertNotNull($calleeConnection);
        $this->assertSame('wss://test.livekit.cloud', $callerConnection['url']);
        $this->assertNotEmpty($callerConnection['token']);
        $this->assertNotSame($callerConnection['token'], $calleeConnection['token']);
    }

    #[Test]
    public function accepted_event_payload_includes_connection_for_caller(): void
    {
        $caller = User::factory()->create(['role' => UserRole::Employee]);
        $callee = User::factory()->create(['role' => UserRole::Employee]);

        $call = Call::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'initiated_by_user_id' => $caller->id,
            'caller_name' => $caller->name,
            'destination_type' => Call::DESTINATION_WORKSPACE_USER,
            'mode' => '1v1',
            'type' => CallType::Video,
            'media_type' => 'video',
            'status' => CallStatus::Active,
            'room_name' => 'call_test',
            'answered_client_instance_id' => 'android-callee',
        ]);

        CallParticipant::query()->create([
            'call_id' => $call->id,
            'user_id' => $caller->id,
            'role' => 'caller',
            'status' => ParticipantStatus::Joined,
            'client_instance_id' => 'android-caller',
        ]);
        CallParticipant::query()->create([
            'call_id' => $call->id,
            'user_id' => $callee->id,
            'role' => 'callee',
            'status' => ParticipantStatus::Joined,
            'client_instance_id' => 'android-callee',
        ]);

        $call->load('participants.user');
        $connection = app(CallConnectionResolver::class)
            ->connectionForParticipant($call, $call->participants->firstWhere('role', 'caller'));

        $event = new CallAccepted($call, (int) $caller->id, $connection);
        $payload = $event->broadcastWith();

        $this->assertSame('active', $payload['status']);
        $this->assertSame('video', $payload['type']);
        $this->assertArrayHasKey('connection', $payload);
        $this->assertSame('wss://test.livekit.cloud', $payload['connection']['url']);
    }
}
