<?php

declare(strict_types=1);

namespace Verteller;

final class StoryParser
{
    /**
     * Parse story file contents into Scenario DTOs.
     *
     * @param string $contents Raw contents of a .story file
     * @return array<string, Scenario> Scenario name => Scenario DTO
     */
    public function parse(string $contents): array
    {
        $saveScenario = function (array &$scenarios, string $name, string $mode, string $text, array $table): void {
            $scenarios[$name] = new Scenario(
                name: $name,
                type: $mode === 'outline' ? ScenarioType::Outline : ScenarioType::Scenario,
                text: $text,
                table: $table,
            );
        };
        $lines = explode(separator: "\n", string: $contents);
        $scenarios = [];
        $current = null;
        $mode = null;
        $insideExamples = false;
        $tableHeaders = [];
        $table = [];
        $text = '';

        foreach ($lines as $line) {
            $trimmedLine = rtrim(string: $line); // keep leading spaces for story text
            $lineNoIndent = trim(string: $line); // for pattern matching

            // Detect Scenario
            if (preg_match(pattern: '/^Scenario:\s*(.+)$/i', subject: $lineNoIndent, matches: $m)) {
                // Save previous scenario if exists
                if ($current !== null) {
                    $saveScenario($scenarios, $current, $mode, $text, $table);
                }

                $current = $m[1];
                $text = "$trimmedLine\n";
                $mode = 'scenario';
                $insideExamples = false;
                $tableHeaders = [];
                $table = [];
            }
            // Detect Scenario Outline
            elseif (preg_match(pattern: '/^Scenario Outline:\s*(.+)$/i', subject: $lineNoIndent, matches: $m)) {
                // Save previous scenario if exists
                if ($current !== null) {
                    $saveScenario($scenarios, $current, $mode, $text, $table);
                }

                $current = $m[1];
                $text = "$trimmedLine\n";
                $mode = 'outline';
                $insideExamples = false;
                $tableHeaders = [];
                $table = [];
            }
            // Process lines inside a scenario/outline
            elseif ($current !== null) {
                $text .= "$trimmedLine\n";

                // Only process tables for outlines
                if ($mode === 'outline') {
                    // Detect Examples start
                    if (preg_match(pattern: '/^Examples:/i', subject: $lineNoIndent)) {
                        $insideExamples = true;
                        continue;
                    }

                    if ($insideExamples && str_starts_with(haystack: $lineNoIndent, needle: '|') && str_ends_with(haystack: $lineNoIndent, needle: '|')) {
                        $cells = array_map(
                            callback: trim(...),
                            array: explode(separator: '|', string: trim(string: $lineNoIndent, characters: '|'))
                        );

                        // First row after Examples is headers
                        if (empty($tableHeaders)) {
                            // Reject headers with empty column names
                            $hasEmptyHeader = false;
                            foreach ($cells as $cell) {
                                if ($cell === '') {
                                    $hasEmptyHeader = true;
                                    break;
                                }
                            }
                            if (!$hasEmptyHeader) {
                                $tableHeaders = $cells;
                            }
                        } else {
                            // Only add row if column count matches headers
                            if (count(value: $cells) === count(value: $tableHeaders)) {
                                $row = array_combine(keys: $tableHeaders, values: $cells);
                                if ($row !== false) {
                                    $table[] = $row;
                                }
                            }
                        }
                    } else {
                        // If non-table line after Examples, stop table parsing
                        if ($insideExamples && $lineNoIndent !== '') {
                            $insideExamples = false;
                        }
                    }
                }
            }
        }

        // Save final scenario
        if ($current !== null) {
            $saveScenario($scenarios, $current, $mode, $text, $table);
        }

        return $scenarios;
    }
}
