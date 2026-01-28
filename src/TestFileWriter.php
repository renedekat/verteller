<?php

declare(strict_types=1);

namespace Verteller;

final readonly class TestFileWriter
{
    private PathNormaliser $pathNormaliser;

    public function __construct(
        private MethodGenerator            $methodGenerator,
        private NamespaceResolverInterface $namespaceResolver,
        private string                     $basePath,
    ) {
        $this->pathNormaliser = new PathNormaliser(basePath: $this->basePath);
    }

    /**
     * Write a new test file.
     *
     * @param string $filePath    Path to the test file
     * @param string $className   Name of the test class
     * @param array<string, Scenario> $scenarios Scenarios to generate methods for
     * @param string $storyPath   Path to the story file (for CoversStory attribute)
     */
    public function writeNew(
        string $filePath,
        string $className,
        array $scenarios,
        string $storyPath,
    ): void {
        $classCode = $this->generateNewFileContent(className: $className, scenarios: $scenarios, storyPath: $storyPath);

        $dir = dirname(path: $filePath);
        if (!is_dir(filename: $dir)) {
            if (!mkdir(directory: $dir, recursive: true) && !is_dir(filename: $dir)) {
                throw new \RuntimeException(message: "Failed to create directory: $dir");
            }
        }

        if (file_put_contents(filename: $filePath, data: $classCode) === false) {
            throw new \RuntimeException(message: "Failed to write file: $filePath");
        }
    }

    /**
     * Generate content for a new test file without writing to disk.
     *
     * @param string $className   Name of the test class
     * @param array<string, Scenario> $scenarios Scenarios to generate methods for
     * @param string $storyPath   Path to the story file (for CoversStory attribute)
     * @return string The generated file content
     */
    public function generateNewFileContent(
        string $className,
        array $scenarios,
        string $storyPath,
    ): string {
        $storyFileRelative = $this->pathNormaliser->toRelative(absolutePath: $storyPath);
        $namespace = $this->namespaceResolver->resolve(storyFilePath: $storyPath, basePath: $this->basePath);

        // Determine required use statements
        $useStatements = [
            'Verteller\\Attributes\\CoversStory',
            'PHPUnit\\Framework\\TestCase',
        ];

        foreach ($scenarios as $scenario) {
            if ($scenario->isOutline()) {
                $useStatements[] = 'PHPUnit\\Framework\\Attributes\\DataProvider';
                break;
            }
        }

        $useStatements = array_unique(array: $useStatements);
        sort(array: $useStatements);

        $useCode = implode(
            separator: "\n",
            array: array_map(callback: static fn(string $use) => "use $use;", array: $useStatements)
        );

        $methodsCode = '';
        foreach ($scenarios as $scenario) {
            $methodsCode .= $this->methodGenerator->generateMethodCode(scenario: $scenario, storyPath: $storyFileRelative);
            $methodsCode .= "\n";
        }

        return <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

$useCode

final class {$className}Test extends TestCase
{
$methodsCode
}
PHP;
    }

    /**
     * Update an existing test file with new scenarios and updated providers.
     *
     * @param string $filePath       Path to the test file
     * @param array<string, Scenario> $scenarios   All scenarios from the story
     * @param array<string, ExtractedMethod> $existingMethods Existing method info
     * @param string $storyPath      Path to the story file
     * @return bool True if changes were written
     */
    public function update(
        string $filePath,
        array $scenarios,
        array $existingMethods,
        string $storyPath,
    ): bool {
        $contents = file_get_contents(filename: $filePath);
        if ($contents === false) {
            return false;
        }

        $updatedContents = $this->generateUpdatedContent(
            contents: $contents,
            scenarios: $scenarios,
            existingMethods: $existingMethods,
            storyPath: $storyPath
        );

        if (file_put_contents(filename: $filePath, data: $updatedContents) === false) {
            throw new \RuntimeException(message: "Failed to write file: $filePath");
        }
        return true;
    }

    /**
     * Generate updated content for an existing test file without writing to disk.
     *
     * @param string $contents       Current file contents
     * @param array<string, Scenario> $scenarios   All scenarios from the story
     * @param array<string, ExtractedMethod> $existingMethods Existing method info
     * @param string $storyPath      Path to the story file
     * @return string The updated file content
     */
    public function generateUpdatedContent(
        string $contents,
        array $scenarios,
        array $existingMethods,
        string $storyPath,
    ): string {
        $storyFileRelative = $this->pathNormaliser->toRelative(absolutePath: $storyPath);

        // 1. Collect new methods to add and update existing providers
        $newMethods = '';
        $needsDataProviderImport = false;

        foreach ($scenarios as $scenarioName => $scenario) {
            // Check if method exists
            $hasExistingMethod = isset($existingMethods[$scenarioName])
                && $existingMethods[$scenarioName]->method !== '';

            if ($hasExistingMethod) {
                // Method exists - only update provider if needed for outlines
                if ($scenario->isOutline()) {
                    $contents = $this->updateProviderTable(
                        contents: $contents,
                        methodName: $existingMethods[$scenarioName]->method,
                        table: $scenario->table
                    );
                }
                continue;
            }

            // Track if we need DataProvider import for new outline scenarios
            if ($scenario->isOutline()) {
                $needsDataProviderImport = true;
            }

            // Generate new method
            $newMethods .= $this->methodGenerator->generateMethodCode(scenario: $scenario, storyPath: $storyFileRelative);
        }

        // 2. Add DataProvider import if needed and not already present
        if ($needsDataProviderImport) {
            $contents = $this->ensureDataProviderImport(contents: $contents);
        }

        // 3. Insert new methods before closing brace (if any)
        if ($newMethods !== '') {
            $contents = $this->insertBeforeClosingBrace(contents: $contents, code: $newMethods);
        }

        return $contents;
    }

    /**
     * Update a provider table in file contents.
     */
    public function updateProviderTable(string $contents, string $methodName, array $table): string
    {
        // Try new naming convention first (base_method_name_provider)
        // Strip 'test_' prefix; fall back to original if preg_replace fails (shouldn't happen with this pattern)
        $stripped = preg_replace(pattern: '/^test_/', replacement: '', subject: $methodName);
        $baseMethodName = $stripped !== null ? $stripped : $methodName;
        $newProviderName = $baseMethodName . '_provider';
        // Also try legacy naming convention (test_method_nameProvider)
        $legacyProviderName = $methodName . 'Provider';

        $newArray = $this->methodGenerator->exportProviderArray(rows: $table);

        // Try new naming convention first
        $pattern = '/(public static function ' . preg_quote(str: $newProviderName, delimiter: '/') . '\(\): array\s*{\s*return\s*)(\[[\s\S]*?])(\s*;\s*})/';
        $result = preg_replace(pattern: $pattern, replacement: '${1}' . $newArray . '${3}', subject: $contents, limit: 1, count: $count);

        if ($result !== null && $count > 0) {
            return $result;
        }

        // Fall back to legacy naming convention
        $pattern = '/(public static function ' . preg_quote(str: $legacyProviderName, delimiter: '/') . '\(\): array\s*{\s*return\s*)(\[[\s\S]*?])(\s*;\s*})/';
        $result = preg_replace(pattern: $pattern, replacement: '${1}' . $newArray . '${3}', subject: $contents);

        return $result ?? $contents;
    }

    /**
     * Ensure DataProvider import is present in file contents.
     */
    public function ensureDataProviderImport(string $contents): string
    {
        $import = 'use PHPUnit\\Framework\\Attributes\\DataProvider;';

        // Check if already imported
        if (str_contains(haystack: $contents, needle: $import)) {
            return $contents;
        }

        // Find the import section (between namespace and class declaration)
        $namespaceEnd = 0;
        if (preg_match(pattern: '/^namespace\s+[^;]+;/m', subject: $contents, matches: $nsMatch, flags: PREG_OFFSET_CAPTURE)) {
            $namespaceEnd = $nsMatch[0][1] + strlen(string: $nsMatch[0][0]);
        }

        $classStart = strlen(string: $contents);
        if (preg_match(pattern: '/\bclass\s+\w+/', subject: $contents, matches: $classMatch, flags: PREG_OFFSET_CAPTURE)) {
            $classStart = $classMatch[0][1];
        }

        // Extract just the import section
        $importSection = substr(string: $contents, offset: $namespaceEnd, length: $classStart - $namespaceEnd);

        // Find the last use statement within the import section
        if (preg_match_all(pattern: '/^use [^;]+;$/m', subject: $importSection, matches: $allMatches, flags: PREG_OFFSET_CAPTURE)
            && !empty($allMatches[0])) {
            $lastUse = end(array: $allMatches[0]);
            $insertPos = $namespaceEnd + $lastUse[1] + strlen(string: $lastUse[0]);

            return substr(string: $contents, offset: 0, length: $insertPos) . "\n" . $import . substr(string: $contents, offset: $insertPos);
        }

        return $contents;
    }

    /**
     * Insert code before the closing brace of a class.
     *
     * Uses PHP tokenizer to find the correct class closing brace,
     * ignoring braces inside strings and comments.
     */
    public function insertBeforeClosingBrace(string $contents, string $code): string
    {
        $tokens = token_get_all(code: $contents);
        $tokenCount = count(value: $tokens);

        // Find the T_CLASS token
        $classTokenIndex = null;
        for ($i = 0; $i < $tokenCount; $i++) {
            if (is_array(value: $tokens[$i]) && $tokens[$i][0] === T_CLASS) {
                $classTokenIndex = $i;
                break;
            }
        }

        if ($classTokenIndex === null) {
            return $contents;
        }

        // Find opening brace after class declaration and count to find closing brace
        $depth = 0;
        $closeBracePos = null;
        $currentPos = 0;

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];
            $tokenLength = is_array(value: $token) ? strlen(string: $token[1]) : strlen(string: $token);

            // Only start counting after we've passed the class token
            if ($i > $classTokenIndex) {
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $closeBracePos = $currentPos;
                        break;
                    }
                }
            }

            $currentPos += $tokenLength;
        }

        if ($closeBracePos === null) {
            return $contents;
        }

        return substr(string: $contents, offset: 0, length: $closeBracePos) . $code . "\n" . substr(string: $contents, offset: $closeBracePos);
    }

    /**
     * Sync multiple hashes in a test file.
     *
     * @param string $filePath    Path to the test file
     * @param array<string, string> $hashUpdates Map of old hash => new hash
     */
    public function syncHashes(string $filePath, array $hashUpdates): void
    {
        $contents = file_get_contents(filename: $filePath);
        if ($contents === false) {
            throw new \RuntimeException(message: "Cannot read file: $filePath");
        }

        foreach ($hashUpdates as $oldHash => $newHash) {
            // Use context-aware regex to only match hashes within CoversStory attributes
            $pattern = '/(#\[CoversStory\([^)]*hash:\s*\')' . preg_quote(str: $oldHash, delimiter: '/') . '(\'\))/';
            $result = preg_replace(pattern: $pattern, replacement: '${1}' . $newHash . '${2}', subject: $contents);
            if ($result !== null) {
                $contents = $result;
            }
        }

        if (file_put_contents(filename: $filePath, data: $contents) === false) {
            throw new \RuntimeException(message: "Failed to write file: $filePath");
        }
    }
}
