<?php

namespace Modules\Workspace\Jobs;

use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCallPushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data,
    ) {}

    public function handle(PushNotificationService $push): void
    {
        $push->sendCallEventToUser($this->userId, $this->title, $this->body, $this->data);
    }
}
