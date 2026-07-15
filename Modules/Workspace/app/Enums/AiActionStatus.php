<?php

namespace Modules\Workspace\Enums;

enum AiActionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Executed = 'executed';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
