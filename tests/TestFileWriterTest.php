<?php

declare(strict_types=1);

namespace Verteller\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Verteller\DefaultNamespaceResolver;
use Verteller\MethodGenerator;
use Verteller\TestFileWriter;

final class TestFileWriterTest extends TestCase
{
    private TestFileWriter $writer;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/verteller_test_' . uniqid();
        mkdir(directory: $this->tempDir);

        $this->writer = new TestFileWriter(
            methodGenerator: new MethodGenerator(),
            namespaceResolver: new DefaultNamespaceResolver(),
            basePath: $this->tempDir,
        );
    }

    protected function tearDown(): void
    {
        $files = glob(pattern: $this->tempDir . '/*');
        foreach ($files as $file) {
            unlink(filename: $file);
        }
        rmdir(directory: $this->tempDir);
    }

    public function test_sync_hashes_replaces_old_hash_with_new(): void
    {
        $filePath = $this->tempDir . '/TestFile.php';
        $contents = <<<'PHP'
<?php

#[CoversStory(storyFile: 'path/to.story', scenario: 'User logs in', hash: 'abc123def456abc123def456abc12345')]
public function test_user_logs_in(): void
{
}
PHP;
        file_put_contents(filename: $filePath, data: $contents);

        $this->writer->syncHashes(filePath: $filePath, hashUpdates: [
            'abc123def456abc123def456abc12345' => 'newhashnewhashnewhashnewhashnewh',
        ]);

        $updated = file_get_contents(filename: $filePath);
        self::assertStringContainsString(needle: 'newhashnewhashnewhashnewhashnewh', haystack: $updated);
        self::assertStringNotContainsString(needle: 'abc123def456abc123def456abc12345', haystack: $updated);
    }

    public function test_sync_hashes_updates_multiple_hashes(): void
    {
        $filePath = $this->tempDir . '/TestFile.php';
        $contents = <<<'PHP'
<?php

#[CoversStory(storyFile: 'path/to.story', scenario: 'User logs in', hash: 'abc123def456abc123def456abc12345')]
public function test_user_logs_in(): void
{
}

#[CoversStory(storyFile: 'path/to.story', scenario: 'Admin access', hash: 'xyz789xyz789xyz789xyz789xyz78901')]
public function test_admin_access(): void
{
}
PHP;
        file_put_contents(filename: $filePath, data: $contents);

        $this->writer->syncHashes(filePath: $filePath, hashUpdates: [
            'abc123def456abc123def456abc12345' => 'hash1hash1hash1hash1hash1hash1ha',
            'xyz789xyz789xyz789xyz789xyz78901' => 'hash2hash2hash2hash2hash2hash2ha',
        ]);

        $updated = file_get_contents(filename: $filePath);
        self::assertStringContainsString(needle: 'hash1hash1hash1hash1hash1hash1ha', haystack: $updated);
        self::assertStringContainsString(needle: 'hash2hash2hash2hash2hash2hash2ha', haystack: $updated);
        self::assertStringNotContainsString(needle: 'abc123def456abc123def456abc12345', haystack: $updated);
        self::assertStringNotContainsString(needle: 'xyz789xyz789xyz789xyz789xyz78901', haystack: $updated);
    }

    public static function insert_before_closing_brace_provider(): array
    {
        return [
            'simple class' => [
                'contents' => <<<'PHP'
<?php
class MyClass
{
}
PHP,
                'code' => '    public function newMethod(): void {}',
                'expectedContains' => "    public function newMethod(): void {}\n}",
            ],
            'class with method' => [
                'contents' => <<<'PHP'
<?php
class MyClass
{
    public function existing(): void
    {
    }
}
PHP,
                'code' => '    public function newMethod(): void {}',
                'expectedContains' => "    }\n    public function newMethod(): void {}\n}",
            ],
            'class with nested closures' => [
                'contents' => <<<'PHP'
<?php
class MyClass
{
    public function existing(): void
    {
        $fn = function () {
            return function () {
                return 42;
            };
        };
    }
}
PHP,
                'code' => '    public function newMethod(): void {}',
                'expectedContains' => "    }\n    public function newMethod(): void {}\n}",
            ],
            'final class' => [
                'contents' => <<<'PHP'
<?php
final class MyClass
{
}
PHP,
                'code' => '    public function newMethod(): void {}',
                'expectedContains' => "    public function newMethod(): void {}\n}",
            ],
            'class with array containing braces in string' => [
                'contents' => <<<'PHP'
<?php
class MyClass
{
    public function getData(): array
    {
        return ['json' => '{"key": "value"}'];
    }
}
PHP,
                'code' => '    public function newMethod(): void {}',
                'expectedContains' => "    }\n    public function newMethod(): void {}\n}",
            ],
            'class with unbalanced brace in string' => [
                'contents' => <<<'PHP'
<?php
class MyClass
{
    public function getMessage(): string
    {
        return "Opening brace: {";
    }
}

// Footer comment
PHP,
                'code' => '    public function newMethod(): void {}',
                'expectedContains' => "    }\n    public function newMethod(): void {}\n}\n\n// Footer",
            ],
        ];
    }

    #[DataProvider('insert_before_closing_brace_provider')]
    public function test_insert_before_closing_brace(string $contents, string $code, string $expectedContains): void
    {
        $result = $this->writer->insertBeforeClosingBrace(contents: $contents, code: $code);

        self::assertStringContainsString(needle: $expectedContains, haystack: $result);
    }

    public function test_insert_before_closing_brace_returns_unchanged_without_class(): void
    {
        $contents = '<?php echo "hello";';

        $result = $this->writer->insertBeforeClosingBrace(contents: $contents, code: 'new code');

        self::assertSame(expected: $contents, actual: $result);
    }

    public function test_insert_before_closing_brace_ignores_content_after_class(): void
    {
        $contents = <<<'PHP'
<?php
class MyClass
{
}
// Some comment after class
PHP;

        $result = $this->writer->insertBeforeClosingBrace(contents: $contents, code: '    public function newMethod(): void {}');

        // The new method should be inserted before the class closing brace, not at end of file
        self::assertStringContainsString(needle: "    public function newMethod(): void {}\n}\n// Some comment", haystack: $result);
    }

    public function test_insert_before_closing_brace_ignores_class_keyword_in_comment(): void
    {
        $contents = <<<'PHP'
<?php
// This is a class example comment
class MyClass
{
}
PHP;

        $result = $this->writer->insertBeforeClosingBrace(contents: $contents, code: '    public function newMethod(): void {}');

        // Should find the actual class declaration, not the word in the comment
        self::assertStringContainsString(needle: "    public function newMethod(): void {}\n}", haystack: $result);
    }

    public function test_insert_before_closing_brace_ignores_class_keyword_in_string(): void
    {
        $contents = <<<'PHP'
<?php
$msg = "This is a class for testing";
class MyClass
{
}
PHP;

        $result = $this->writer->insertBeforeClosingBrace(contents: $contents, code: '    public function newMethod(): void {}');

        // Should find the actual class declaration, not the word in the string
        self::assertStringContainsString(needle: "    public function newMethod(): void {}\n}", haystack: $result);
    }

    public function test_ensure_data_provider_import_adds_import(): void
    {
        $contents = <<<'PHP'
<?php
namespace App\Tests;

use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
}
PHP;

        $result = $this->writer->ensureDataProviderImport(contents: $contents);

        self::assertStringContainsString(needle: 'use PHPUnit\Framework\Attributes\DataProvider;', haystack: $result);
    }

    public function test_ensure_data_provider_import_does_not_duplicate(): void
    {
        $contents = <<<'PHP'
<?php
namespace App\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
}
PHP;

        $result = $this->writer->ensureDataProviderImport(contents: $contents);

        // Should only appear once
        self::assertSame(expected: 1, actual: substr_count(haystack: $result, needle: 'use PHPUnit\Framework\Attributes\DataProvider;'));
    }

    public function test_ensure_data_provider_import_ignores_use_in_class_body(): void
    {
        $contents = <<<'PHP'
<?php
namespace App\Tests;

use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
    use SomeTrait;
}
PHP;

        $result = $this->writer->ensureDataProviderImport(contents: $contents);

        // Should add the import after the existing use statement, not after the trait use
        $importPos = strpos(haystack: $result, needle: 'use PHPUnit\Framework\Attributes\DataProvider;');
        $classPos = strpos(haystack: $result, needle: 'class MyTest');

        self::assertNotFalse(condition: $importPos, message: 'DataProvider import should be added');
        self::assertLessThan($classPos, $importPos, message: 'Import should appear before class declaration');
    }

    public function test_ensure_data_provider_import_ignores_use_in_heredoc(): void
    {
        $contents = <<<'PHP'
<?php
namespace App\Tests;

use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
    public function getData(): string
    {
        return <<<SQL
use some_database;
SELECT * FROM users;
SQL;
    }
}
PHP;

        $result = $this->writer->ensureDataProviderImport(contents: $contents);

        // Should add the import in the import section, not after the heredoc 'use'
        $importPos = strpos(haystack: $result, needle: 'use PHPUnit\Framework\Attributes\DataProvider;');
        $classPos = strpos(haystack: $result, needle: 'class MyTest');

        self::assertNotFalse(condition: $importPos, message: 'DataProvider import should be added');
        self::assertLessThan($classPos, $importPos, message: 'Import should appear before class declaration');
    }
}
