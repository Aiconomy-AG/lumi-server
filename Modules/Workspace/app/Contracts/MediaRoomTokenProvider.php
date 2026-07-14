<?php

namespace Modules\Workspace\Contracts;

use App\Models\User;
use Modules\Workspace\Models\Call;

interface MediaRoomTokenProvider
{
    /** @return array{url: string, token: string} */
    public function connectionFor(Call $call, User $user, string $clientInstanceId): array;
}
