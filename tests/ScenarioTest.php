<?php

declare(strict_types=1);

namespace Verteller\Tests;

use PHPUnit\Framework\TestCase;
use Verteller\Scenario;
use Verteller\ScenarioType;

final class ScenarioTest extends TestCase
{
    public function test_body_hash_returns_md5_hash(): void
    {
        $scenario = new Scenario(
            name: 'Test scenario',
            type: ScenarioType::Scenario,
            text: "Scenario: Test scenario\n    Given a user\n    When they act\n    Then result",
        );

        $hash = $scenario->bodyHash();

        // MD5 is 32 hex characters
        self::assertMatchesRegularExpression(pattern: '/^[a-f0-9]{32}$/', string: $hash);
    }

    public function test_body_hash_excludes_scenario_name_line(): void
    {
        $scenario1 = new Scenario(
            name: 'Name One',
            type: ScenarioType::Scenario,
            text: "Scenario: Name One\n    Given a user\n    Then result",
        );

        $scenario2 = new Scenario(
            name: 'Name Two',
            type: ScenarioType::Scenario,
            text: "Scenario: Name Two\n    Given a user\n    Then result",
        );

        // Same body, different names should have same hash
        self::assertSame(expected: $scenario1->bodyHash(), actual: $scenario2->bodyHash());
    }

    public function test_body_hash_excludes_examples_table(): void
    {
        $scenario1 = new Scenario(
            name: 'Outline',
            type: ScenarioType::Outline,
            text: "Scenario Outline: Outline\n    Given <value>\n\n    Examples:\n        | value |\n        | one   |",
            table: [['value' => 'one']],
        );

        $scenario2 = new Scenario(
            name: 'Outline',
            type: ScenarioType::Outline,
            text: "Scenario Outline: Outline\n    Given <value>\n\n    Examples:\n        | value |\n        | two   |",
            table: [['value' => 'two']],
        );

        // Same body steps, different examples should have same hash
        self::assertSame(expected: $scenario1->bodyHash(), actual: $scenario2->bodyHash());
    }

    public function test_body_hash_changes_when_steps_change(): void
    {
        $scenario1 = new Scenario(
            name: 'Test',
            type: ScenarioType::Scenario,
            text: "Scenario: Test\n    Given a user\n    Then result",
        );

        $scenario2 = new Scenario(
            name: 'Test',
            type: ScenarioType::Scenario,
            text: "Scenario: Test\n    Given a different user\n    Then result",
        );

        self::assertNotSame(expected: $scenario1->bodyHash(), actual: $scenario2->bodyHash());
    }

    public function test_body_hash_is_consistent(): void
    {
        $scenario = new Scenario(
            name: 'Test',
            type: ScenarioType::Scenario,
            text: "Scenario: Test\n    Given a user\n    Then result",
        );

        // Multiple calls return same hash
        self::assertSame(expected: $scenario->bodyHash(), actual: $scenario->bodyHash());
    }

    public function test_body_hash_normalizes_whitespace(): void
    {
        $scenario1 = new Scenario(
            name: 'Test',
            type: ScenarioType::Scenario,
            text: "Scenario: Test\n    Given a user\n    Then result",
        );

        $scenario2 = new Scenario(
            name: 'Test',
            type: ScenarioType::Scenario,
            text: "Scenario: Test\n        Given a user\n        Then result\n\n",
        );

        // Different whitespace, same content should have same hash
        self::assertSame(expected: $scenario1->bodyHash(), actual: $scenario2->bodyHash());
    }
}
