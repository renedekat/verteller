# Verteller

Story-driven test scaffolding for PHPUnit. Sync Gherkin-like `.story` files to test stubs automatically.

## Architecture

This is a standalone PHP library with no external dependencies beyond PHPUnit (dev only).

### Key Principles

1. **No magic** - All dependencies are explicitly typed via constructor injection
2. **Interface-based** - `NamespaceResolverInterface` allows custom namespace resolution
3. **Immutable DTOs** - `Scenario`, `SyncEvent` are readonly value objects
4. **Single responsibility** - Each class has one clear purpose

## Project Structure

```
verteller/
├── bin/
│   └── verteller.php          # CLI entry point
├── src/
│   ├── Attributes/
│   │   └── CoversStory.php    # PHPUnit attribute for test traceability
│   ├── DefaultNamespaceResolver.php
│   ├── MethodExtractor.php    # Extracts existing CoversStory methods from test files
│   ├── MethodGenerator.php    # Generates test method code
│   ├── NamespaceResolverInterface.php
│   ├── Scenario.php           # DTO for parsed scenario
│   ├── ScenarioType.php       # Enum: Scenario | Outline
│   ├── StoryParser.php        # Parses .story file contents
│   ├── SyncEvent.php          # DTO for sync events
│   ├── SyncEventType.php      # Enum: Generated | Updated | HashSynced | Warning | Error
│   ├── SyncResult.php         # Collects sync events, generates summary
│   ├── SyncRunner.php         # Main orchestrator
│   └── TestFileWriter.php     # Writes/updates test files
├── tests/
│   ├── MethodExtractorTest.php
│   ├── MethodGeneratorTest.php
│   ├── ScenarioTest.php
│   ├── StoryParserTest.php
│   └── TestFileWriterTest.php
├── composer.json
├── phpunit.xml
├── README.md
└── LICENSE
```

## Development Commands

```bash
# Install dependencies
composer install

# Run tests
composer test
./vendor/bin/phpunit --testdox

# Test CLI
php bin/verteller.php --help
php bin/verteller.php --dry-run /path/to/project
```

## Code Conventions

### Naming

Use **British English** spelling for all identifiers (variables, methods, functions, classes) and user-facing text:

| American (don't use) | British (use this) |
|---------------------|-------------------|
| serialize           | serialise         |
| deserialize         | deserialise       |
| sanitize            | sanitise          |
| normalize           | normalise         |
| initialize          | initialise        |
| authorize           | authorise         |
| color (in code)     | colour            |

**Exceptions** (keep American spelling):
- Third-party library APIs

### PHP

- PSR-4 autoloading: `Verteller\` maps to `src/`
- Use named parameters for clarity: `new Scenario(name: 'Test', type: ScenarioType::Scenario, ...)`
- Readonly classes/properties where appropriate
- **Tell, don't ask** - Put behaviour in objects, not in calling code
- **DTOs over arrays** - Use typed DTOs for data that crosses boundaries

## Key Classes

### SyncRunner

Main orchestrator. Finds story files, syncs them to test stubs.

```php
$runner = new SyncRunner(
    basePath: '/path/to/project',
    storyDir: 'src/Domains',  // Where to scan for .story files (relative to basePath)
    namespaceResolver: $resolver,  // Optional custom resolver
);
$runner->run(dryRun: true);       // Preview changes
$runner->run(validate: true);     // CI mode: fail if changes needed
$runner->run(syncHashes: true);   // Only update hashes (no new methods)
$runner->printSummary();
```

### StoryParser

Parses `.story` file contents into `Scenario` DTOs.

```php
$parser = new StoryParser();
$scenarios = $parser->parse($storyContents);
// Returns: ['Scenario name' => Scenario, ...]
```

### MethodGenerator

Generates test method code from scenarios.

```php
$generator = new MethodGenerator();
$code = $generator->generateMethodCode($scenario, 'path/to/story.story');
```

### MethodExtractor

Uses reflection to extract existing `#[CoversStory]` methods from test files.

```php
$extractor = new MethodExtractor();
$methods = $extractor->extract('/path/to/TestFile.php');
// Returns: ['Scenario name' => ['method' => 'test_name', 'hash' => 'abc123'], ...]
```

### NamespaceResolverInterface

Implement this to customise namespace and test directory resolution for your project structure:

```php
class CustomResolver implements NamespaceResolverInterface
{
    public function resolve(string $storyFilePath, string $basePath): string
    {
        return 'My\\Custom\\Tests\\Namespace';
    }

    public function resolveTestDir(string $storyFilePath, string $basePath): string
    {
        return $basePath . '/tests/Story';
    }
}
```

## Writing Tests

Tests use PHPUnit 11+ and live in `tests/`.

### TDD: Red-Green-Refactor

Always follow **Test-Driven Development** when making changes:

1. **RED** - Write a failing test first that demonstrates the bug or specifies the new behaviour
2. **GREEN** - Write the minimum code to make the test pass
3. **REFACTOR** - Clean up the code while keeping tests green

Never commit a fix without a corresponding test that would have failed before the fix.

### Standard test setup

```php
<?php

declare(strict_types=1);

namespace Verteller\Tests;

use PHPUnit\Framework\TestCase;
use Verteller\StoryParser;

final class StoryParserTest extends TestCase
{
    private StoryParser $parser;

    protected function setUp(): void
    {
        $this->parser = new StoryParser();
    }

    public function test_parses_simple_scenario(): void
    {
        $scenarios = $this->parser->parse(contents: $contents);

        self::assertCount(expectedCount: 1, haystack: $scenarios);
    }
}
```

### Test file isolation

Tests that create temporary files should clean up in `tearDown()`:

```php
protected function setUp(): void
{
    $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid();
    mkdir(directory: $this->tempDir);
}

protected function tearDown(): void
{
    // Clean up temp files
    $files = glob(pattern: $this->tempDir . '/*');
    foreach ($files as $file) {
        unlink(filename: $file);
    }
    rmdir(directory: $this->tempDir);
}
```

## Git Commit Conventions

### Format

```
type: short subject line (max 50 chars)

Detailed body paragraph explaining what and why (not how).
```

### Rules

- Use conventional commit types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `style`, `perf`
- No Claude attribution - NEVER include "Generated with Claude Code" or "Co-Authored-By: Claude"
- Keep first line under 50 characters
- No scope in parentheses - Use `refactor: subject` not `refactor(parser): subject`
- Use heredoc for multi-line commit messages
