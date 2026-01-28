<?php

declare(strict_types=1);

namespace Verteller\Tests;

use PHPUnit\Framework\TestCase;
use Verteller\SyncRunner;

final class SyncRunnerTest extends TestCase
{
    public function test_throws_exception_for_nonexistent_base_path(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        new SyncRunner(basePath: '/nonexistent/path/that/does/not/exist');
    }

    public function test_accepts_valid_directory(): void
    {
        $runner = new SyncRunner(basePath: sys_get_temp_dir());

        // Should not throw - just verify it constructs successfully
        self::assertInstanceOf(expected: SyncRunner::class, actual: $runner);
    }
}
