<?php

declare(strict_types=1);

/**
 * Example custom runner for Verteller.
 *
 * This script demonstrates how to configure Verteller for a custom project structure.
 * Copy and adapt this for your own project.
 */

require __DIR__ . '/../../vendor/autoload.php';

use Verteller\NamespaceResolverInterface;
use Verteller\SyncRunner;

$basePath = dirname(__DIR__);

// Define how story paths map to test namespaces and directories
$resolver = new class implements NamespaceResolverInterface {
    public function resolve(string $storyFilePath, string $basePath): string
    {
        // src/Auth/Stories/Login.story → App\Auth\Tests\Story
        $relative = str_replace($basePath . '/src/', '', dirname($storyFilePath));
        $parts = array_filter(
            explode(DIRECTORY_SEPARATOR, $relative),
            fn($part) => $part !== 'Stories'
        );

        return 'App\\' . implode('\\', $parts) . '\\Tests\\Story';
    }

    public function resolveTestDir(string $storyFilePath, string $basePath): string
    {
        // src/Auth/Stories/Login.story → src/Auth/Tests/Story/
        $dir = dirname($storyFilePath);

        return str_replace(
            DIRECTORY_SEPARATOR . 'Stories',
            DIRECTORY_SEPARATOR . 'Tests' . DIRECTORY_SEPARATOR . 'Story',
            $dir
        );
    }
};

// Parse CLI arguments
$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$validate = in_array('--validate', $args, true);
$syncHashes = in_array('--sync-hashes', $args, true);

if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    echo <<<HELP
Example Verteller Runner

Usage: php bin/sync-stories.php [options]

Options:
  --dry-run   Preview changes without writing files
  --validate  Exit with error if changes are needed (for CI)
  --sync-hashes  Only update CoversStory hashes (no new methods or providers)
  --help, -h  Show this help message

HELP;
    exit(0);
}

echo "Syncing stories in: $basePath\n";
if ($syncHashes) {
    if ($dryRun) {
        echo "Mode: SYNC HASHES DRY RUN\n";
    } else {
        echo "Mode: SYNC HASHES\n";
    }
} elseif ($dryRun) {
    echo "Mode: DRY RUN\n";
} elseif ($validate) {
    echo "Mode: VALIDATE\n";
}
echo "\n";

// Create runner with custom namespace resolver and story directory
$runner = new SyncRunner(
    basePath: $basePath,
    storyDir: 'src',
    namespaceResolver: $resolver
);

$runner->run(dryRun: $dryRun, validate: $validate, syncHashes: $syncHashes);
$runner->printSummary();

exit($runner->getExitCode(validate: $validate));
