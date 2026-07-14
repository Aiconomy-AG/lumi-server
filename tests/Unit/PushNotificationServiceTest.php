<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\Message;
use Mockery;
use Tests\TestCase;

class PushNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_string_data_to_fcm(): void
    {
        if (! class_exists(\Kreait\Firebase\Messaging\CloudMessage::class)) {
            $this->markTestSkipped('Kreait Firebase SDK is not installed.');
        }

        $messaging = Mockery::mock(Messaging::class);
        $messaging
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (Message $message): bool {
                $payload = $message->jsonSerialize();

                return $payload['token'] === 'valid-token'
                    && $payload['notification']['title'] === 'Task assigned'
                    && $payload['notification']['body'] === 'You were assigned: Wire notifications'
                    && $payload['data'] === ['task_id' => '123', 'urgent' => '1'];
            }))
            ->andReturn([]);

        $service = new PushNotificationService($messaging);

        $service->sendToToken('valid-token', 'Task assigned', 'You were assigned: Wire notifications', [
            'task_id' => 123,
            'urgent' => true,
        ]);
    }

    public function test_it_deletes_invalid_or_expired_tokens(): void
    {
        if (! interface_exists(Messaging::class)) {
            $this->markTestSkipped('Kreait Firebase SDK is not installed.');
        }

        $user = User::factory()->create(['role' => UserRole::Employee]);

        DeviceToken::query()->create([
            'user_id' => $user->id,
            'token' => 'expired-token',
            'platform' => 'android',
            'device_id' => 'test-device',
        ]);

        $messaging = Mockery::mock(Messaging::class);
        $messaging
            ->shouldReceive('send')
            ->once()
            ->andThrow(NotFound::becauseTokenNotFound('expired-token'));

        $service = new PushNotificationService($messaging);

        $service->sendToToken('expired-token', 'Task assigned', 'Body');

        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'expired-token',
        ]);
    }
}
