<?php

namespace App\Console\Commands;

use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class PushTestCommand extends Command
{
    protected $signature = 'push:test {userId} {--title=Test} {--body=Hello}';

    protected $description = 'Send a test push notification to all device tokens for a user.';

    public function handle(PushNotificationService $pushNotificationService): int
    {
        $pushNotificationService->sendToUser(
            (int) $this->argument('userId'),
            (string) $this->option('title'),
            (string) $this->option('body'),
            ['type' => 'test'],
        );

        $this->info('Push notification test dispatched to the user device tokens.');

        return self::SUCCESS;
    }
}
