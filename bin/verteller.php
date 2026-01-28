<?php

declare(strict_types=1);

// Find autoloader
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',       // When installed as a dependency
    __DIR__ . '/../../../autoload.php',        // When installed globally
];

$autoloader = null;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        $autoloader = $path;
        break;
    }
}

if ($autoloader === null) {
    fwrite(STDERR, "Could not find autoloader. Run 'composer install' first.\n");
    exit(1);
}

require $autoloader;

use Verteller\SyncRunner;

// Parse arguments
$args = array_slice($argv, 1);
$options = [
    'dry-run' => false,
    'validate' => false,
    'sync-hashes' => false,
    'help' => false,
];
$basePath = null;

foreach ($args as $arg) {
    if ($arg === '--dry-run') {
        $options['dry-run'] = true;
    } elseif ($arg === '--validate') {
        $options['validate'] = true;
    } elseif ($arg === '--sync-hashes') {
        $options['sync-hashes'] = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        $options['help'] = true;
    } elseif (!str_starts_with($arg, '-')) {
        $basePath = $arg;
    }
}

if ($options['help']) {
    echo <<<HELP
Verteller - Story-driven test scaffolding for PHPUnit

Usage: verteller [options] [path]

Options:
  --dry-run      Preview changes without writing files
  --validate     Exit with error if changes are needed (for CI)
  --sync-hashes  Only update CoversStory hashes (no new methods or providers)
  --help, -h     Show this help message

Arguments:
  path           Project root directory (default: current directory)

Examples:
  verteller                    Sync stories in current directory
  verteller --dry-run          Preview what would be changed
  verteller --validate         CI mode: fail if tests are out of sync
  verteller --sync-hashes      Update hashes only (after reviewing changes)
  verteller /path/to/project   Sync stories in specified directory

HELP;
    exit(0);
}

if ($basePath === null) {
    $basePath = getcwd();
    if ($basePath === false) {
        fwrite(STDERR, "Error: Unable to determine current directory.\n");
        exit(1);
    }
}

if (!is_dir($basePath)) {
    fwrite(STDERR, "Error: '$basePath' is not a valid directory.\n");
    exit(1);
}

echo "Syncing stories in: $basePath\n";

if ($options['sync-hashes']) {
    if ($options['dry-run']) {
        echo "Mode: SYNC HASHES DRY RUN (previewing hash updates only)\n";
    } else {
        echo "Mode: SYNC HASHES (updating hashes only)\n";
    }
} elseif ($options['dry-run']) {
    echo "Mode: DRY RUN (no files will be written)\n";
} elseif ($options['validate']) {
    echo "Mode: VALIDATE (will fail if changes needed)\n";
}

echo "\n";

$runner = new SyncRunner(basePath: $basePath);
$result = $runner->run(dryRun: $options['dry-run'], validate: $options['validate'], syncHashes: $options['sync-hashes']);

$result->printSummary();

$exitCode = $result->getExitCode(validate: $options['validate']);
if ($exitCode === 0) {
    echo "\nSync complete.\n";
} elseif ($options['validate'] && $result->hasChanges()) {
    echo "\nValidation failed: Test files are out of sync with stories.\n";
} elseif ($result->hasErrors()) {
    echo "\nSync completed with errors.\n";
} else {
    echo "\nSync completed with warnings.\n";
}

exit($exitCode);
