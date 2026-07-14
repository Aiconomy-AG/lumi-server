<?php

namespace Modules\Workspace\Domain\Messages;

enum MessageType: string
{
    case Text = 'text';
    case Call = 'call';
}
