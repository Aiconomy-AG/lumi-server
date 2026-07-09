<?php

namespace App\Jobs;

use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    public function handle(): void
    {
        try {
            app(PushNotificationService::class)->sendToUser(
                $this->userId,
                $this->title,
                $this->body,
                $this->data,
            );
        } catch (Throwable $exception) {
            Log::warning('Push notification job failed without blocking the source action.', [
                'user_id' => $this->userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
