<?php

namespace Modules\Workspace\Domain\Messages;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Call = 'call';
    case System = 'system';
}
