<?php

namespace Modules\Workspace\Domain\Calls;

enum CallStatus: string
{
    case Ringing = 'ringing';
    case Active = 'active';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Missed = 'missed';
    case Ended = 'ended';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return ! in_array($this, [self::Ringing, self::Active], true);
    }
}
