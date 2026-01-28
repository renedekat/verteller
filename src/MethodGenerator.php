<?php

declare(strict_types=1);

namespace Verteller;

final class MethodGenerator
{
    /**
     * Generate test method code for a regular scenario.
     */
    public function generateScenarioMethod(Scenario $scenario, string $storyPath): string
    {
        $baseMethodName = $this->toMethodName(name: $scenario->name);
        $methodName = 'test_' . $baseMethodName;
        $storyText = $this->formatStoryText(text: $scenario->text);
        $hash = $scenario->bodyHash();
        $escapedName = $this->escapeForPhpString(value: $scenario->name);

        return <<<PHP

    #[CoversStory(storyFile: '$storyPath', scenario: '$escapedName', hash: '$hash')]
    public function $methodName(): void
    {
        /*
$storyText
        */
        self::markTestIncomplete('This test is auto-generated from the story and needs implementation.');
    }
PHP;
    }

    /**
     * Generate test method code for a scenario outline (with data provider).
     *
     * If the table is empty, generates a simple method without data provider.
     */
    public function generateOutlineMethod(Scenario $scenario, string $storyPath): string
    {
        // Empty table: generate like a regular scenario
        if (empty($scenario->table)) {
            return $this->generateScenarioMethod(scenario: $scenario, storyPath: $storyPath);
        }

        $baseMethodName = $this->toMethodName(name: $scenario->name);
        $methodName = 'test_' . $baseMethodName;
        $providerName = $baseMethodName . '_provider';
        $storyText = $this->formatStoryText(text: $this->stripExamples(text: $scenario->text));
        $rowsExport = $this->exportProviderArray(rows: $scenario->table);
        $hash = $scenario->bodyHash();
        $columnNames = array_keys($scenario->table[0]);
        $parameters = $this->generateTypedParameters(columnNames: $columnNames);
        $escapedName = $this->escapeForPhpString(value: $scenario->name);

        return <<<PHP

    #[CoversStory(storyFile: '$storyPath', scenario: '$escapedName', hash: '$hash')]
    #[DataProvider('$providerName')]
    public function $methodName($parameters): void
    {
        /*
$storyText
        */
        self::markTestIncomplete('This test is auto-generated from the story outline and needs implementation.');
    }

    public static function $providerName(): array
    {
        return $rowsExport;
    }
PHP;
    }

    /**
     * Generate method code based on scenario type.
     */
    public function generateMethodCode(Scenario $scenario, string $storyPath): string
    {
        if ($scenario->isOutline()) {
            return $this->generateOutlineMethod(scenario: $scenario, storyPath: $storyPath);
        }

        return $this->generateScenarioMethod(scenario: $scenario, storyPath: $storyPath);
    }

    /**
     * Export table rows as PHP array code for data providers.
     */
    public function exportProviderArray(array $rows): string
    {
        $lines = [];
        $lines[] = '[';

        foreach ($rows as $row) {
            $items = [];
            foreach ($row as $key => $value) {
                $escapedKey = $this->escapeForPhpString(value: (string) $key);
                $escapedValue = $this->escapeForPhpString(value: $value);
                $items[] = sprintf("'%s' => '%s'", $escapedKey, $escapedValue);
            }
            $lines[] = '            [' . implode(separator: ', ', array: $items) . '],';
        }

        $lines[] = '        ]';
        return implode(separator: "\n", array: $lines);
    }

    /**
     * Convert scenario name to valid PHP method name.
     *
     * Replaces spaces with underscores and removes any characters
     * that are not valid in PHP identifiers (letters, numbers, underscores).
     */
    public function toMethodName(string $name): string
    {
        $lowered = strtolower(string: $name);
        $withUnderscores = str_replace(search: ' ', replace: '_', subject: $lowered);
        $sanitised = preg_replace(pattern: '/[^a-z0-9_]/', replacement: '', subject: $withUnderscores);

        // Fallback to 'unnamed' if result is empty
        return $sanitised !== '' ? $sanitised : 'unnamed';
    }

    /**
     * Escape a string for use in a single-quoted PHP string literal.
     *
     * Only backslash and single quote need escaping in single-quoted strings.
     */
    private function escapeForPhpString(string $value): string
    {
        return str_replace(
            search: ['\\', "'"],
            replace: ['\\\\', "\\'"],
            subject: $value
        );
    }

    /**
     * Generate typed parameter list from column names.
     */
    private function generateTypedParameters(array $columnNames): string
    {
        return implode(
            separator: ', ',
            array: array_map(
                callback: fn(string $name) => 'string $' . $this->toParameterName(name: $name),
                array: $columnNames
            )
        );
    }

    /**
     * Convert a column name to a valid PHP parameter name.
     */
    private function toParameterName(string $name): string
    {
        $lowered = strtolower(string: $name);
        $sanitised = preg_replace(pattern: '/[^a-z0-9_]/', replacement: '', subject: $lowered);

        return $sanitised !== '' ? $sanitised : 'param';
    }

    /**
     * Format story text with consistent indentation for embedding in comments.
     */
    private function formatStoryText(string $text): string
    {
        $lines = explode(separator: "\n", string: rtrim(string: $text));
        $indent = '        '; // 8 spaces to align with comment block

        return implode(separator: "\n", array: array_map(
            callback: fn(string $line) => $indent . trim(string: $line),
            array: $lines
        ));
    }

    /**
     * Strip Examples section from scenario outline text.
     */
    private function stripExamples(string $text): string
    {
        // Remove everything from "Examples:" to end
        $pos = stripos(haystack: $text, needle: 'Examples:');
        if ($pos !== false) {
            $text = substr(string: $text, offset: 0, length: $pos);
        }

        return rtrim(string: $text);
    }
}
