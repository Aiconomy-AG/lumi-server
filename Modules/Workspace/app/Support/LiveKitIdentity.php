<?php

namespace Modules\Workspace\Support;

class LiveKitIdentity
{
    public static function forUser(int $userId, string $clientInstanceId): string
    {
        return sprintf('user:%d:client:%s', $userId, $clientInstanceId);
    }
}
