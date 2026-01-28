<?php

declare(strict_types=1);

namespace Verteller;

interface NamespaceResolverInterface
{
    /**
     * Resolve the PHP namespace for a test file based on the story file path.
     *
     * @param string $storyFilePath Absolute path to the .story file
     * @param string $basePath      Project root directory
     * @return string               PHP namespace for the generated test class
     */
    public function resolve(string $storyFilePath, string $basePath): string;

    /**
     * Resolve the test directory for a story file.
     *
     * @param string $storyFilePath Absolute path to the .story file
     * @param string $basePath      Project root directory
     * @return string               Absolute path to the test directory
     */
    public function resolveTestDir(string $storyFilePath, string $basePath): string;
}
