<?php

namespace Modules\Workspace\Domain\Calls;

enum CallType: string
{
    case Audio = 'audio';
    case Video = 'video';
}
