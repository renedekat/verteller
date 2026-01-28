<?php

declare(strict_types=1);

namespace Verteller\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Verteller\MethodGenerator;
use Verteller\Scenario;
use Verteller\ScenarioType;

final class MethodGeneratorTest extends TestCase
{
    private MethodGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new MethodGenerator();
    }

    public function test_generates_scenario_method(): void
    {
        $scenario = new Scenario(
            name: 'User logs in',
            type: ScenarioType::Scenario,
            text: "Scenario: User logs in\n    Given valid credentials\n    Then success",
        );

        $code = $this->generator->generateScenarioMethod(scenario: $scenario, storyPath: 'path/to/story.story');

        self::assertStringContainsString(needle: "#[CoversStory(storyFile: 'path/to/story.story', scenario: 'User logs in', hash:", haystack: $code);
        self::assertStringContainsString(needle: 'public function test_user_logs_in(): void', haystack: $code);
        self::assertStringContainsString(needle: 'self::markTestIncomplete', haystack: $code);
        self::assertStringContainsString(needle: 'Given valid credentials', haystack: $code);
    }

    public function test_generates_outline_method_with_provider(): void
    {
        $scenario = new Scenario(
            name: 'Access control',
            type: ScenarioType::Outline,
            text: "Scenario Outline: Access control\n    Given role <role>",
            table: [
                ['role' => 'admin', 'access' => 'granted'],
                ['role' => 'guest', 'access' => 'denied'],
            ],
        );

        $code = $this->generator->generateOutlineMethod(scenario: $scenario, storyPath: 'path/to/story.story');

        self::assertStringContainsString(needle: "#[CoversStory(storyFile: 'path/to/story.story', scenario: 'Access control', hash:", haystack: $code);
        self::assertStringContainsString(needle: "#[DataProvider('access_control_provider')]", haystack: $code);
        self::assertStringContainsString(needle: 'public function test_access_control(string $role, string $access): void', haystack: $code);
        self::assertStringContainsString(needle: 'public static function access_control_provider(): array', haystack: $code);
        self::assertStringContainsString(needle: "'role' => 'admin'", haystack: $code);
        self::assertStringContainsString(needle: "'access' => 'granted'", haystack: $code);
    }

    public function test_generates_outline_method_with_empty_table_like_regular_scenario(): void
    {
        $scenario = new Scenario(
            name: 'Empty examples',
            type: ScenarioType::Outline,
            text: "Scenario Outline: Empty examples\n    Given some step",
            table: [],
        );

        $code = $this->generator->generateOutlineMethod(scenario: $scenario, storyPath: 'path/to/story.story');

        // Should generate like a regular scenario - no DataProvider, no provider function
        self::assertStringContainsString(needle: 'public function test_empty_examples(): void', haystack: $code);
        self::assertStringNotContainsString(needle: '#[DataProvider', haystack: $code);
        self::assertStringNotContainsString(needle: '_provider(): array', haystack: $code);
    }

    public function test_sanitises_column_names_for_parameters(): void
    {
        $scenario = new Scenario(
            name: 'Test with special columns',
            type: ScenarioType::Outline,
            text: "Scenario Outline: Test\n    Given <user-name> and <email address>",
            table: [
                ['user-name' => 'john', 'email address' => 'john@example.com'],
            ],
        );

        $code = $this->generator->generateOutlineMethod(scenario: $scenario, storyPath: 'test.story');

        // Column names should be sanitised to valid PHP identifiers
        self::assertStringContainsString(needle: 'string $username', haystack: $code);
        self::assertStringContainsString(needle: 'string $emailaddress', haystack: $code);
        // Original invalid names should not appear as parameters
        self::assertStringNotContainsString(needle: '$user-name', haystack: $code);
        self::assertStringNotContainsString(needle: '$email address', haystack: $code);
    }

    public function test_generate_method_code_routes_to_correct_generator(): void
    {
        $scenarioRegular = new Scenario(
            name: 'Simple test',
            type: ScenarioType::Scenario,
            text: 'Scenario: Simple test',
        );

        $scenarioOutline = new Scenario(
            name: 'Outline test',
            type: ScenarioType::Outline,
            text: 'Scenario Outline: Outline test',
            table: [['key' => 'value']],
        );

        $regularCode = $this->generator->generateMethodCode(scenario: $scenarioRegular, storyPath: 'test.story');
        $outlineCode = $this->generator->generateMethodCode(scenario: $scenarioOutline, storyPath: 'test.story');

        self::assertStringNotContainsString(needle: 'DataProvider', haystack: $regularCode);
        self::assertStringContainsString(needle: 'DataProvider', haystack: $outlineCode);
    }

    public function test_exports_provider_array(): void
    {
        $table = [
            ['name' => 'Alice', 'age' => '30'],
            ['name' => 'Bob', 'age' => '25'],
        ];

        $code = $this->generator->exportProviderArray(rows: $table);

        self::assertStringContainsString(needle: '[', haystack: $code);
        self::assertStringContainsString(needle: "'name' => 'Alice'", haystack: $code);
        self::assertStringContainsString(needle: "'age' => '30'", haystack: $code);
        self::assertStringContainsString(needle: "'name' => 'Bob'", haystack: $code);
        // Verify flat array structure (no double-wrapping)
        self::assertStringNotContainsString(needle: '[[', haystack: $code);
        self::assertStringNotContainsString(needle: ']]', haystack: $code);
    }

    public static function php_string_escaping_provider(): array
    {
        return [
            'single quote' => [
                'input' => "It's a test",
                'expected' => "It\\'s a test",
            ],
            'backslash' => [
                'input' => 'path\\to\\file',
                'expected' => 'path\\\\to\\\\file',
            ],
            'backslash and single quote' => [
                'input' => "It's a path\\test",
                'expected' => "It\\'s a path\\\\test",
            ],
            'double quote unchanged' => [
                'input' => 'He said "hello"',
                'expected' => 'He said "hello"',
            ],
            'backslash before quote' => [
                'input' => "test\\'value",
                'expected' => "test\\\\\\'value",
            ],
            'multiple single quotes' => [
                'input' => "don't won't can't",
                'expected' => "don\\'t won\\'t can\\'t",
            ],
            'trailing backslash' => [
                'input' => 'value\\',
                'expected' => 'value\\\\',
            ],
            'empty string' => [
                'input' => '',
                'expected' => '',
            ],
            'no special characters' => [
                'input' => 'simple text',
                'expected' => 'simple text',
            ],
        ];
    }

    #[DataProvider('php_string_escaping_provider')]
    public function test_escapes_special_characters_in_provider(string $input, string $expected): void
    {
        $table = [
            ['text' => $input],
        ];

        $code = $this->generator->exportProviderArray(rows: $table);

        self::assertStringContainsString(needle: "'text' => '$expected'", haystack: $code);
    }

    public function test_escapes_special_characters_in_column_keys(): void
    {
        $table = [
            ["user's name" => 'john', "path\\to" => 'value'],
        ];

        $code = $this->generator->exportProviderArray(rows: $table);

        // Keys should be escaped for single-quoted strings
        self::assertStringContainsString(needle: "'user\\'s name' => 'john'", haystack: $code);
        self::assertStringContainsString(needle: "'path\\\\to' => 'value'", haystack: $code);
    }

    public static function method_name_provider(): array
    {
        return [
            'spaces to underscores' => [
                'input' => 'User logs in',
                'expected' => 'user_logs_in',
            ],
            'simple lowercase' => [
                'input' => 'Simple test',
                'expected' => 'simple_test',
            ],
            'multiple spaces' => [
                'input' => 'A B C',
                'expected' => 'a_b_c',
            ],
            'single quote removed' => [
                'input' => "Login fail's with invalid password",
                'expected' => 'login_fails_with_invalid_password',
            ],
            'multiple special characters' => [
                'input' => "It's a test! (with symbols)",
                'expected' => 'its_a_test_with_symbols',
            ],
            'numbers preserved' => [
                'input' => 'Test case 123',
                'expected' => 'test_case_123',
            ],
            'hyphens removed' => [
                'input' => 'user-logs-in',
                'expected' => 'userlogsin',
            ],
            'double quotes removed' => [
                'input' => 'Login with "valid" credentials',
                'expected' => 'login_with_valid_credentials',
            ],
            'all special characters fallback to unnamed' => [
                'input' => '!@#$%^&*()',
                'expected' => 'unnamed',
            ],
            'empty string fallback to unnamed' => [
                'input' => '',
                'expected' => 'unnamed',
            ],
        ];
    }

    #[DataProvider('method_name_provider')]
    public function test_to_method_name(string $input, string $expected): void
    {
        self::assertSame(expected: $expected, actual: $this->generator->toMethodName(name: $input));
    }

    public function test_normalizes_indentation_in_story_text(): void
    {
        $scenario = new Scenario(
            name: 'Test',
            type: ScenarioType::Scenario,
            text: "Scenario: Test\n  Given step one\n    And step two",
        );

        $code = $this->generator->generateScenarioMethod(scenario: $scenario, storyPath: 'test.story');

        // Each line should have consistent 8-space indentation
        self::assertStringContainsString(needle: "        Scenario: Test\n", haystack: $code);
        self::assertStringContainsString(needle: "        Given step one\n", haystack: $code);
        self::assertStringContainsString(needle: "        And step two\n", haystack: $code);
    }

    public function test_strips_examples_from_outline(): void
    {
        $scenario = new Scenario(
            name: 'Outline',
            type: ScenarioType::Outline,
            text: "Scenario Outline: Outline\n    Given <value>\n\n    Examples:\n        | value |\n        | test  |",
            table: [['value' => 'test']],
        );

        $code = $this->generator->generateOutlineMethod(scenario: $scenario, storyPath: 'test.story');

        self::assertStringContainsString(needle: 'Given <value>', haystack: $code);
        self::assertStringNotContainsString(needle: 'Examples:', haystack: $code);
        self::assertStringNotContainsString(needle: '| value |', haystack: $code);
    }

    public function test_includes_body_hash_in_scenario_method(): void
    {
        $scenario = new Scenario(
            name: 'User logs in',
            type: ScenarioType::Scenario,
            text: "Scenario: User logs in\n    Given valid credentials\n    Then success",
        );

        $code = $this->generator->generateScenarioMethod(scenario: $scenario, storyPath: 'path/to/story.story');

        // Should include hash parameter
        self::assertStringContainsString(needle: 'hash:', haystack: $code);
        self::assertMatchesRegularExpression(pattern: "/hash: '[a-f0-9]{32}'/", string: $code);
    }

    public function test_includes_body_hash_in_outline_method(): void
    {
        $scenario = new Scenario(
            name: 'Access control',
            type: ScenarioType::Outline,
            text: "Scenario Outline: Access control\n    Given role <role>",
            table: [['role' => 'admin']],
        );

        $code = $this->generator->generateOutlineMethod(scenario: $scenario, storyPath: 'path/to/story.story');

        // Should include hash parameter
        self::assertStringContainsString(needle: 'hash:', haystack: $code);
        self::assertMatchesRegularExpression(pattern: "/hash: '[a-f0-9]{32}'/", string: $code);
    }
}
