<?php

declare(strict_types=1);

namespace Verteller;

final readonly class Scenario
{
    public function __construct(
        public string $name,
        public ScenarioType $type,
        public string $text,
        public array $table = [],
    ) {}

    public function isOutline(): bool
    {
        return $this->type === ScenarioType::Outline;
    }

    /**
     * Compute MD5 hash of the scenario body (steps only).
     * Excludes scenario name line and Examples table.
     */
    public function bodyHash(): string
    {
        $body = $this->text;

        // Remove first line (scenario name)
        $lines = explode(separator: "\n", string: $body);
        array_shift(array: $lines);

        // Remove Examples section
        $filtered = [];
        foreach ($lines as $line) {
            if (preg_match(pattern: '/^\s*Examples:/i', subject: $line)) {
                break;
            }
            $filtered[] = trim(string: $line);
        }

        // Normalise: trim each line, remove empty lines, join
        $normalised = implode(
            separator: "\n",
            array: array_filter(array: $filtered, callback: fn($line) => $line !== '')
        );

        return md5(string: $normalised);
    }
}
