<?php

declare(strict_types=1);

namespace Verteller;

/**
 * Default namespace resolver for projects using the domain-driven structure:
 * src/Domains/{Domain}/Stories/*.story → App\Domains\{Domain}\Backend\Tests\Story
 */
final readonly class DefaultNamespaceResolver implements NamespaceResolverInterface
{
    public function __construct(
        private string $rootNamespace = 'App',
    ) {}

    public function resolve(string $storyFilePath, string $basePath): string
    {
        // Normalise paths to handle trailing slashes and separators
        $normalisedBasePath = rtrim(string: $basePath, characters: DIRECTORY_SEPARATOR);
        $storyDir = dirname(path: $storyFilePath);

        // Get the path of the story relative to src/Domains
        $relativePath = str_replace(
            search: $normalisedBasePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR,
            replace: '',
            subject: $storyDir
        );

        // Split path into parts and remove 'Stories'
        $parts = array_filter(
            array: explode(separator: DIRECTORY_SEPARATOR, string: $relativePath),
            callback: fn($part) => $part !== 'Stories'
        );

        // Join remaining parts with backslashes
        $namespace = implode(separator: '\\', array: $parts);

        // Prepend root namespace and append Backend\Tests\Story
        return $this->rootNamespace . '\\' . $namespace . '\\Backend\\Tests\\Story';
    }

    public function resolveTestDir(string $storyFilePath, string $basePath): string
    {
        // Default: replace Stories with Backend/Tests/Story
        return str_replace(
            search: DIRECTORY_SEPARATOR . 'Stories',
            replace: DIRECTORY_SEPARATOR . 'Backend' . DIRECTORY_SEPARATOR . 'Tests' . DIRECTORY_SEPARATOR . 'Story',
            subject: dirname(path: $storyFilePath)
        );
    }
}
