<?php

declare(strict_types=1);

namespace Verteller;

enum SyncEventType: string
{
    case Generated = 'generated';
    case Updated = 'updated';
    case HashSynced = 'hash_synced';
    case Warning = 'warning';
    case Error = 'error';
}
