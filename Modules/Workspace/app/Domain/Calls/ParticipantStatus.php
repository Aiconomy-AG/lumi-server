<?php

namespace Modules\Workspace\Domain\Calls;

enum ParticipantStatus: string
{
    case Joined = 'joined';
    case Ringing = 'ringing';
    case Declined = 'declined';
    case Missed = 'missed';
    case Left = 'left';
}
