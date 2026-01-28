# Verteller Example

This example demonstrates how to use Verteller with a custom project structure.

## Structure

```
example/
├── bin/
│   └── sync-stories.php      # Custom runner with namespace resolver
├── src/
│   └── Auth/
│       └── Stories/
│           └── Login.story   # Story file with scenarios
├── tests/
│   └── Story/
│       └── Auth/
│           └── LoginTest.php # Generated test file
└── README.md
```

## How it works

1. **Story file** (`src/Auth/Stories/Login.story`) contains three scenarios:
   - A simple scenario for successful login
   - A simple scenario for failed login
   - A scenario outline with examples for validation errors

2. **Custom runner** (`bin/sync-stories.php`) defines how paths map to namespaces:
   - `src/Auth/Stories/Login.story` → `Tests\Story\Auth\LoginTest`

3. **Generated test** (`tests/Story/Auth/LoginTest.php`) contains:
   - Test methods for each scenario
   - `#[CoversStory]` attributes linking tests to scenarios
   - A data provider for the scenario outline

## Running the example

From the verteller root directory:

```bash
# Preview what would be synced
php example/bin/sync-stories.php --dry-run

# Actually sync (regenerate the test file)
php example/bin/sync-stories.php

# Validate mode (for CI)
php example/bin/sync-stories.php --validate
```

## Adapting for your project

1. Copy `bin/sync-stories.php` to your project
2. Modify the namespace resolver to match your structure
3. Update the `$basePath` if needed
4. Run it!
