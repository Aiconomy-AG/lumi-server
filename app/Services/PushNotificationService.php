<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Support\DeviceTokenPlatform;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PushNotificationService
{
    public function __construct(
        private readonly Messaging $messaging
    ) {}

    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::query()
            ->where('user_id', $userId)
            ->whereNull('invalidated_at')
            ->whereIn('platform', [
                DeviceTokenPlatform::FCM_ANDROID,
                DeviceTokenPlatform::LEGACY_ANDROID,
                DeviceTokenPlatform::LEGACY_IOS,
                DeviceTokenPlatform::WEB_PUSH,
            ])
            ->pluck('token');

        if ($tokens->isEmpty()) {
            Log::info('Push notification skipped because user has no device tokens.', [
                'user_id' => $userId,
            ]);

            return;
        }

        $tokens->each(fn (string $token) => $this->sendToToken($token, $title, $body, $data));
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): void
    {
        $message = CloudMessage::new()
            ->withToken($token)
            ->withNotification(Notification::create($title, $body))
            ->withData($this->stringifyData($data));

        $this->sendMessage($message, $token);
    }

    public function sendCallEventToToken(string $token, string $title, string $body, array $data): void
    {
        $payload = ['title' => $title, 'body' => $body, ...$data];

        $message = CloudMessage::new()
            ->withToken($token)
            ->withData($this->stringifyData($payload))
            ->withHighestPossiblePriority()
            ->withDefaultSounds();

        $this->sendMessage($message, $token);
    }

    public function sendCallEventToUser(int $userId, string $title, string $body, array $data): void
    {
        $payload = ['title' => $title, 'body' => $body, ...$data];

        DeviceToken::query()
            ->where('user_id', $userId)
            ->whereNull('invalidated_at')
            ->whereIn('platform', DeviceTokenPlatform::fcmPlatforms())
            ->get(['token', 'platform'])
            ->each(function (DeviceToken $deviceToken) use ($title, $body, $payload): void {
                $message = CloudMessage::new()
                    ->withToken($deviceToken->token)
                    ->withData($this->stringifyData($payload))
                    ->withHighestPossiblePriority()
                    ->withDefaultSounds();

                $this->sendMessage($message, $deviceToken->token);
            });
    }

    public function sendCallAlertToUser(int $userId, string $title, string $body, array $data): void
    {
        $payload = ['title' => $title, 'body' => $body, ...$data];

        DeviceToken::query()
            ->where('user_id', $userId)
            ->whereNull('invalidated_at')
            ->where('platform', DeviceTokenPlatform::LEGACY_IOS)
            ->get(['token'])
            ->each(function (DeviceToken $deviceToken) use ($title, $body, $payload): void {
                $message = CloudMessage::new()
                    ->withToken($deviceToken->token)
                    ->withData($this->stringifyData($payload))
                    ->withNotification(Notification::create($title, $body))
                    ->withHighestPossiblePriority()
                    ->withDefaultSounds();

                $this->sendMessage($message, $deviceToken->token);
            });
    }

    private function sendMessage(CloudMessage $message, string $token): void
    {
        try {
            $this->messaging->send($message);
        } catch (NotFound $exception) {
            $this->deleteToken($token);

            Log::warning('Push notification token was invalid or expired and has been deleted.', [
                'token' => $this->tokenFingerprint($token),
                'error' => $exception->getMessage(),
            ]);
        } catch (MessagingException|FirebaseException $exception) {
            Log::warning('Push notification failed.', [
                'token' => $this->tokenFingerprint($token),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function stringifyData(array $data): array
    {
        return collect($data)
            ->mapWithKeys(fn (mixed $value, string|int $key): array => [(string) $key => (string) $value])
            ->all();
    }

    private function deleteToken(string $token): void
    {
        DeviceToken::query()
            ->where('token', $token)
            ->delete();
    }

    private function tokenFingerprint(string $token): string
    {
        if (strlen($token) <= 12) {
            return hash('sha256', $token);
        }

        return substr($token, 0, 6).'...'.substr($token, -6);
    }
}
