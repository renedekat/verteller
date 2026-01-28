<?php

declare(strict_types=1);

namespace Verteller\Tests;

use PHPUnit\Framework\TestCase;
use Verteller\ScenarioType;
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
        $contents = <<<STORY
Scenario: User logs in
    Given a registered user
    When they enter valid credentials
    Then they should be logged in
STORY;

        $scenarios = $this->parser->parse(contents: $contents);

        self::assertCount(expectedCount: 1, haystack: $scenarios);
        self::assertArrayHasKey(key: 'User logs in', array: $scenarios);

        $scenario = $scenarios['User logs in'];
        self::assertSame(expected: 'User logs in', actual: $scenario->name);
        self::assertSame(expected: ScenarioType::Scenario, actual: $scenario->type);
        self::assertFalse(condition: $scenario->isOutline());
        self::assertStringContainsString(needle: 'Given a registered user', haystack: $scenario->text);
        self::assertEmpty(actual: $scenario->table);
    }

    public function test_parses_multiple_scenarios(): void
    {
        $contents = <<<STORY
Scenario: First scenario
    Given step one
    Then result one

Scenario: Second scenario
    Given step two
    Then result two
STORY;

        $scenarios = $this->parser->parse(contents: $contents);

        self::assertCount(expectedCount: 2, haystack: $scenarios);
        self::assertArrayHasKey(key: 'First scenario', array: $scenarios);
        self::assertArrayHasKey(key: 'Second scenario', array: $scenarios);
    }

    public function test_parses_scenario_outline_with_examples(): void
    {
        $contents = <<<STORY
Scenario Outline: User authentication
    Given a user with role <role>
    When they attempt to access <resource>
    Then they should see <result>

    Examples:
        | role    | resource | result   |
        | admin   | settings | success  |
        | guest   | settings | denied   |
        | user    | profile  | success  |
STORY;

        $scenarios = $this->parser->parse(contents: $contents);

        self::assertCount(expectedCount: 1, haystack: $scenarios);
        self::assertArrayHasKey(key: 'User authentication', array: $scenarios);

        $scenario = $scenarios['User authentication'];
        self::assertSame(expected: 'User authentication', actual: $scenario->name);
        self::assertSame(expected: ScenarioType::Outline, actual: $scenario->type);
        self::assertTrue(condition: $scenario->isOutline());
        self::assertCount(expectedCount: 3, haystack: $scenario->table);

        self::assertSame(expected: [
            'role' => 'admin',
            'resource' => 'settings',
            'result' => 'success',
        ], actual: $scenario->table[0]);

        self::assertSame(expected: [
            'role' => 'guest',
            'resource' => 'settings',
            'result' => 'denied',
        ], actual: $scenario->table[1]);
    }

    public function test_parses_mixed_scenarios_and_outlines(): void
    {
        $contents = <<<STORY
Scenario: Simple login
    Given valid credentials
    Then user is logged in

Scenario Outline: Access control
    Given user with role <role>
    Then access is <access>

    Examples:
        | role  | access  |
        | admin | granted |
        | guest | denied  |
STORY;

        $scenarios = $this->parser->parse(contents: $contents);

        self::assertCount(expectedCount: 2, haystack: $scenarios);
        self::assertFalse(condition: $scenarios['Simple login']->isOutline());
        self::assertTrue(condition: $scenarios['Access control']->isOutline());
        self::assertCount(expectedCount: 2, haystack: $scenarios['Access control']->table);
    }

    public function test_returns_empty_array_for_empty_content(): void
    {
        $scenarios = $this->parser->parse(contents: '');

        self::assertEmpty(actual: $scenarios);
    }

    public function test_returns_empty_array_for_content_without_scenarios(): void
    {
        $contents = <<<STORY
Feature: Some feature
    As a user
    I want to do something
    So that I get value
STORY;

        $scenarios = $this->parser->parse(contents: $contents);

        self::assertEmpty(actual: $scenarios);
    }

    public function test_rejects_empty_table_headers(): void
    {
        $contents = <<<STORY
Scenario Outline: Test with empty headers
    Given some step with <value>

    Examples:
        |  |  |
        | a | b |
STORY;

        $scenarios = $this->parser->parse(contents: $contents);

        // Table should be empty because headers are empty strings
        self::assertEmpty(actual: $scenarios['Test with empty headers']->table);
    }

    public function test_rejects_rows_with_empty_header_names(): void
    {
        $contents = <<<STORY
Scenario Outline: Test with partial empty headers
    Given some step with <value>

    Examples:
        | valid |  | another |
        | a | b | c |
STORY;

        $scenarios = $this->parser->parse(contents: $contents);

        // Table should be empty because one header is empty
        self::assertEmpty(actual: $scenarios['Test with partial empty headers']->table);
    }
}
