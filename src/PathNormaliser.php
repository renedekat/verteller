<?php

declare(strict_types=1);

namespace Verteller;

/**
 * Utility for normalising paths relative to a base path.
 */
final readonly class PathNormaliser
{
    private string $normalisedBasePath;

    public function __construct(
        string $basePath,
    ) {
        // Normalise base path by removing trailing separators
        $this->normalisedBasePath = rtrim(string: $basePath, characters: DIRECTORY_SEPARATOR);
    }

    /**
     * Convert an absolute path to a path relative to the base path.
     */
    public function toRelative(string $absolutePath): string
    {
        $prefix = $this->normalisedBasePath . DIRECTORY_SEPARATOR;

        if (str_starts_with(haystack: $absolutePath, needle: $prefix)) {
            return substr(string: $absolutePath, offset: strlen(string: $prefix));
        }

        // Path doesn't start with base path, return as-is
        return $absolutePath;
    }
}
