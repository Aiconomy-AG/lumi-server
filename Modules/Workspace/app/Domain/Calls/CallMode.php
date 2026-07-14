<?php

namespace Modules\Workspace\Domain\Calls;

enum CallMode: string
{
    case OneToOne = '1v1';
    case Group = 'group';
}
