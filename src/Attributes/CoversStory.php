<?php

declare(strict_types=1);

namespace Verteller\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class CoversStory
{
    public function __construct(
        public string $storyFile,   // relative path to the .story file
        public string $scenario,    // scenario name inside the story
        public ?string $hash = null // MD5 hash of scenario body for change detection
    ) {}
}
