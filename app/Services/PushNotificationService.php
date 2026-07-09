<?php

namespace App\Services;

use App\Models\DeviceToken;
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

        try {
            $this->messaging->send($message);
        } catch (NotFound $exception) {
            $this->deleteToken($token);

            Log::warning('Push notification token was invalid or expired and has been deleted.', [
                'token' => $token,
                'error' => $exception->getMessage(),
            ]);
        } catch (MessagingException|FirebaseException $exception) {
            Log::warning('Push notification failed.', [
                'token' => $token,
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
}
