<?php

declare(strict_types=1);

namespace Verteller\Tests;

use PHPUnit\Framework\TestCase;
use Verteller\MethodExtractor;

final class MethodExtractorTest extends TestCase
{
    private MethodExtractor $extractor;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->extractor = new MethodExtractor();
        $this->tempDir = sys_get_temp_dir() . '/method_extractor_test_' . uniqid();
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

    public function test_extracts_hash_from_attribute(): void
    {
        $testFile = $this->tempDir . '/TestWithHash.php';
        file_put_contents(filename: $testFile, data: <<<'PHP'
<?php
namespace Verteller\Tests\Fixtures;

use PHPUnit\Framework\TestCase;
use Verteller\Attributes\CoversStory;

class TestWithHash extends TestCase
{
    #[CoversStory(storyFile: 'path/to/story.story', scenario: 'My scenario', hash: 'abc123def456')]
    public function test_my_scenario(): void {}
}
PHP);

        $methods = $this->extractor->extract(filePath: $testFile);

        self::assertArrayHasKey(key: 'My scenario', array: $methods);
        self::assertSame(expected: 'abc123def456', actual: $methods['My scenario']->hash);
    }

    public function test_extracts_null_hash_when_not_present(): void
    {
        $testFile = $this->tempDir . '/TestWithoutHash.php';
        file_put_contents(filename: $testFile, data: <<<'PHP'
<?php
namespace Verteller\Tests\Fixtures;

use PHPUnit\Framework\TestCase;
use Verteller\Attributes\CoversStory;

class TestWithoutHash extends TestCase
{
    #[CoversStory(storyFile: 'path/to/story.story', scenario: 'My scenario')]
    public function test_my_scenario(): void {}
}
PHP);

        $methods = $this->extractor->extract(filePath: $testFile);

        self::assertArrayHasKey(key: 'My scenario', array: $methods);
        self::assertNull(actual: $methods['My scenario']->hash);
    }

    public function test_extracts_method_name_and_scenario(): void
    {
        $testFile = $this->tempDir . '/BasicTest.php';
        file_put_contents(filename: $testFile, data: <<<'PHP'
<?php
namespace Verteller\Tests\Fixtures;

use PHPUnit\Framework\TestCase;
use Verteller\Attributes\CoversStory;

class BasicTest extends TestCase
{
    #[CoversStory(storyFile: 'test.story', scenario: 'User logs in')]
    public function test_user_logs_in(): void {}
}
PHP);

        $methods = $this->extractor->extract(filePath: $testFile);

        self::assertArrayHasKey(key: 'User logs in', array: $methods);
        self::assertSame(expected: 'test_user_logs_in', actual: $methods['User logs in']->method);
    }

    public function test_rejects_file_with_function_call_in_global_scope(): void
    {
        // A file with a function call in global scope should be rejected as unsafe
        // even if it extends TestCase - this prevents code execution via require_once
        $markerFile = $this->tempDir . '/executed_marker.txt';
        $testFile = $this->tempDir . '/UnsafeFunctionCall.php';
        $escapedMarker = addslashes($markerFile);
        file_put_contents(filename: $testFile, data: <<<PHP
<?php
namespace Verteller\Tests\Fixtures;

use PHPUnit\Framework\TestCase;
use Verteller\Attributes\CoversStory;

file_put_contents('$escapedMarker', 'executed');

class UnsafeFunctionCall extends TestCase
{
    #[CoversStory(storyFile: 'test.story', scenario: 'Test scenario')]
    public function test_scenario(): void {}
}
PHP);

        $this->extractor->extract(filePath: $testFile);

        // The marker file should NOT exist - if it does, unsafe code was executed
        self::assertFileDoesNotExist(filename: $markerFile, message: 'Unsafe global scope code was executed via require_once');
    }

    public function test_filters_non_array_rows_from_provider_snapshot(): void
    {
        // A provider that returns malformed data (non-array rows) should be handled gracefully
        $testFile = $this->tempDir . '/MalformedProvider.php';
        file_put_contents(filename: $testFile, data: <<<'PHP'
<?php
namespace Verteller\Tests\Fixtures;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Verteller\Attributes\CoversStory;

class MalformedProvider extends TestCase
{
    #[CoversStory(storyFile: 'test.story', scenario: 'Test scenario')]
    #[DataProvider('malformed_provider')]
    public function test_scenario(string $value): void {}

    public static function malformed_provider(): array
    {
        return [
            'valid' => ['key' => 'value'],
            'invalid_scalar' => 'not an array',
            'invalid_nested' => [['key' => 'value']],
        ];
    }
}
PHP);

        $methods = $this->extractor->extract(filePath: $testFile);

        // Should extract the method (provider snapshot may vary, but should not crash)
        self::assertArrayHasKey(key: 'Test scenario', array: $methods);
    }

    public function test_handles_file_with_complex_tokens(): void
    {
        // File with various single-character tokens that could trip up array access
        $testFile = $this->tempDir . '/ComplexTokenTest.php';
        file_put_contents(filename: $testFile, data: <<<'PHP'
<?php
namespace Verteller\Tests\Fixtures;

use PHPUnit\Framework\TestCase;
use Verteller\Attributes\CoversStory;

// Various tokens: ; { } ( ) [ ] = , . :: ->
final class ComplexTokenTest extends TestCase
{
    private array $data = ['key' => 'value'];

    #[CoversStory(storyFile: 'test.story', scenario: 'Complex scenario')]
    public function test_complex_scenario(): void
    {
        $x = fn() => $this->data['key'];
    }
}
PHP);

        $methods = $this->extractor->extract(filePath: $testFile);

        self::assertArrayHasKey(key: 'Complex scenario', array: $methods);
        self::assertSame(expected: 'test_complex_scenario', actual: $methods['Complex scenario']->method);
    }
}
