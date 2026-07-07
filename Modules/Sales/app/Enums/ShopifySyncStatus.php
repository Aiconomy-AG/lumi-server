<?php
namespace Modules\Sales\Enums;

enum ShopifySyncStatus: string
{
    case Synced = 'synced';
    case Unsynced = 'unsynced';
    case Syncing = 'syncing';
    case Error = 'error';
}
