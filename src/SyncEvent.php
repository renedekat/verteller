<?php

declare(strict_types=1);

namespace Verteller;

final readonly class SyncEvent
{
    public function __construct(
        public SyncEventType $type,
        public string $message,
        public array $details = [],
    ) {}
}
