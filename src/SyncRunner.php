<?php

declare(strict_types=1);

namespace Verteller;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class SyncRunner
{
    private readonly string $basePath;
    private readonly string $storyDir;
    private readonly StoryParser $parser;
    private readonly MethodExtractor $extractor;
    private readonly MethodGenerator $generator;
    private readonly TestFileWriter $writer;
    private readonly NamespaceResolverInterface $namespaceResolver;

    private SyncResult $result;

    /**
     * @param string $basePath Project root directory
     * @param string $storyDir Directory to scan for .story files (relative to basePath)
     * @param NamespaceResolverInterface|null $namespaceResolver Custom namespace resolver
     */
    public function __construct(
        string $basePath,
        string $storyDir = 'src/Domains',
        ?NamespaceResolverInterface $namespaceResolver = null,
    ) {
        $resolvedPath = realpath(path: $basePath);
        if ($resolvedPath === false) {
            throw new \InvalidArgumentException(message: "Base path '$basePath' does not exist or is not accessible.");
        }
        $this->basePath = rtrim(string: $resolvedPath, characters: DIRECTORY_SEPARATOR);
        $this->storyDir = $storyDir;
        $this->namespaceResolver = $namespaceResolver ?? new DefaultNamespaceResolver();

        $this->parser = new StoryParser();
        $this->extractor = new MethodExtractor();
        $this->generator = new MethodGenerator();
        $this->writer = new TestFileWriter(
            methodGenerator: $this->generator,
            namespaceResolver: $this->namespaceResolver,
            basePath: $this->basePath,
        );

        $this->result = new SyncResult();
    }

    /**
     * Run the sync process.
     *
     * @param bool $dryRun     If true, don't write any files
     * @param bool $validate   If true, exit with error if changes needed (for CI)
     * @param bool $syncHashes If true, only sync hashes (no new methods or provider updates)
     */
    public function run(bool $dryRun = false, bool $validate = false, bool $syncHashes = false): SyncResult
    {
        $this->result = new SyncResult();

        // In validate mode, we act like dry-run but check for needed changes
        $effectiveDryRun = $dryRun || $validate;

        foreach ($this->findStoryFiles() as $storyFile) {
            $testsDir = $this->resolveTestDir(storyFile: $storyFile);
            $this->syncStoryFile(storyFile: $storyFile, testsDir: $testsDir, dryRun: $effectiveDryRun, validate: $validate, syncHashes: $syncHashes);
        }

        return $this->result;
    }

    /**
     * Find all .story files in the configured story directory.
     *
     * @return string[]
     */
    public function findStoryFiles(): array
    {
        $storyPath = $this->basePath . DIRECTORY_SEPARATOR . $this->storyDir;

        if (!is_dir(filename: $storyPath) || !is_readable(filename: $storyPath)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            iterator: new RecursiveDirectoryIterator(directory: $storyPath, flags: FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with(haystack: $file->getFilename(), needle: '.story')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Resolve the test directory for a story file.
     */
    private function resolveTestDir(string $storyFile): string
    {
        return $this->namespaceResolver->resolveTestDir(storyFilePath: $storyFile, basePath: $this->basePath);
    }

    /**
     * Print a summary of the sync result.
     */
    public function printSummary(): void
    {
        $this->result->printSummary();
    }

    /**
     * Get the exit code for the sync result.
     */
    public function getExitCode(bool $validate = false): int
    {
        return $this->result->getExitCode(validate: $validate);
    }

    /**
     * Sync a single story file.
     */
    public function syncStoryFile(string $storyFile, string $testsDir, bool $dryRun = false, bool $validate = false, bool $syncHashes = false): void
    {
        $storyName = pathinfo(path: $storyFile, flags: PATHINFO_FILENAME);
        $storyContents = file_get_contents(filename: $storyFile);
        if ($storyContents === false) {
            $this->result->addError(message: "Cannot read story file: $storyFile");
            return;
        }
        $scenarios = $this->parser->parse(contents: $storyContents);

        if (empty($scenarios)) {
            if (!$syncHashes) {
                $this->result->addError(message: "No scenarios found in $storyFile");
            }
            return;
        }

        $testFile = "$testsDir/{$storyName}Test.php";
        $fileExists = file_exists(filename: $testFile);

        // In sync-hashes mode, skip files that don't exist
        if ($syncHashes && !$fileExists) {
            return;
        }

        $existingMethods = $fileExists ? $this->extractor->extract(filePath: $testFile) : [];

        // Sync-hashes mode: ONLY update hashes where mismatches exist
        if ($syncHashes) {
            $hashUpdates = [];
            $syncedScenarios = [];

            foreach ($scenarios as $scenarioName => $scenario) {
                if (isset($existingMethods[$scenarioName])) {
                    $existingHash = $existingMethods[$scenarioName]->hash;
                    $currentHash = $scenario->bodyHash();

                    if ($existingHash !== null && $existingHash !== $currentHash) {
                        $hashUpdates[$existingHash] = $currentHash;
                        $syncedScenarios[] = $scenarioName;
                    }
                }
            }

            if (!empty($hashUpdates)) {
                $this->result->addHashSynced(message: $testFile, details: ['synced_hashes' => $syncedScenarios]);

                if (!$dryRun) {
                    $this->writer->syncHashes(filePath: $testFile, hashUpdates: $hashUpdates);
                }
            }

            return;
        }

        // Normal sync mode
        $addedMethods = [];
        $updatedProviders = [];
        $hashMismatches = [];

        // Check existing methods vs story
        foreach ($existingMethods as $scenario => $extracted) {
            if (!isset($scenarios[$scenario])) {
                $this->result->addWarning(
                    message: "Test method '{$extracted->method}' exists but scenario '$scenario' no longer exists in $storyFile"
                );
            }
        }

        // Determine new methods, updated providers, or hash mismatches
        foreach ($scenarios as $scenarioName => $scenario) {
            if (!isset($existingMethods[$scenarioName])) {
                // New scenario → needs full method
                $existingMethods[$scenarioName] = new ExtractedMethod(method: '', providerSnapshot: null, hash: null);
                $methodName = 'test_' . $this->generator->toMethodName(name: $scenarioName);
                $addedMethods[] = $methodName . '()';
            } else {
                // Check hash mismatch
                $existingHash = $existingMethods[$scenarioName]->hash;
                $currentHash = $scenario->bodyHash();

                if ($existingHash !== null && $existingHash !== $currentHash) {
                    $hashMismatches[] = $scenarioName;
                }

                if ($scenario->isOutline()) {
                    // Outline table changed → update provider
                    $existingProviderSnapshot = $existingMethods[$scenarioName]->providerSnapshot;
                    if ($existingProviderSnapshot !== serialize(value: $scenario->table)) {
                        $baseMethodName = $this->generator->toMethodName(name: $scenarioName);
                        $updatedProviders[] = $baseMethodName . '_provider()';
                    }
                }
            }
        }

        // Report hash mismatches
        foreach ($hashMismatches as $scenarioName) {
            $message = "Scenario '$scenarioName' body has changed in $storyFile - test may need review";
            if ($validate) {
                $this->result->addError(message: $message);
            } else {
                $this->result->addWarning(message: $message);
            }
        }

        $hasChanges = !empty($addedMethods) || !empty($updatedProviders);

        if ($hasChanges) {
            $details = [];
            if (!empty($addedMethods)) {
                $details['added'] = $addedMethods;
            }
            if (!empty($updatedProviders)) {
                $details['provider_updated'] = $updatedProviders;
            }

            if ($dryRun) {
                // Generate preview of new methods for dry-run mode
                $storyFileRelative = str_replace(
                    search: $this->basePath . DIRECTORY_SEPARATOR,
                    replace: '',
                    subject: $storyFile
                );
                $newMethodsPreview = '';
                foreach ($scenarios as $scenarioName => $scenario) {
                    // Only show methods that don't exist yet
                    if (!isset($existingMethods[$scenarioName]) || $existingMethods[$scenarioName]->method === '') {
                        $newMethodsPreview .= $this->generator->generateMethodCode(
                            scenario: $scenario,
                            storyPath: $storyFileRelative
                        ) . "\n";
                    }
                }
                if ($newMethodsPreview !== '') {
                    $details['preview'] = $newMethodsPreview;
                }
            }

            if ($fileExists) {
                $this->result->addUpdated(message: $testFile, details: $details);
            } else {
                $this->result->addGenerated(message: $testFile, details: $details);
            }

            if (!$dryRun) {
                if ($fileExists) {
                    $this->writer->update(filePath: $testFile, scenarios: $scenarios, existingMethods: $existingMethods, storyPath: $storyFile);
                } else {
                    $this->writer->writeNew(filePath: $testFile, className: $storyName, scenarios: $scenarios, storyPath: $storyFile);
                }
            }
        }
    }
}
