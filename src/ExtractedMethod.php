<?php

declare(strict_types=1);

namespace Verteller;

/**
 * Represents a method extracted from a test file that has a CoversStory attribute.
 */
final readonly class ExtractedMethod
{
    public function __construct(
        public string $method,
        public ?string $providerSnapshot,
        public ?string $hash,
    ) {}
}
