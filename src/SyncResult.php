<?php

declare(strict_types=1);

namespace Verteller;

final class SyncResult
{
    /** @var SyncEvent[] */
    private array $events = [];
    private bool $hasErrors = false;
    private bool $hasWarnings = false;
    private bool $hasChanges = false;

    public function addGenerated(string $message, array $details = []): void
    {
        $this->events[] = new SyncEvent(type: SyncEventType::Generated, message: $message, details: $details);
        $this->hasChanges = true;
    }

    public function addUpdated(string $message, array $details = []): void
    {
        $this->events[] = new SyncEvent(type: SyncEventType::Updated, message: $message, details: $details);
        $this->hasChanges = true;
    }

    public function addWarning(string $message, array $details = []): void
    {
        $this->events[] = new SyncEvent(type: SyncEventType::Warning, message: $message, details: $details);
        $this->hasWarnings = true;
    }

    public function addError(string $message, array $details = []): void
    {
        $this->events[] = new SyncEvent(type: SyncEventType::Error, message: $message, details: $details);
        $this->hasErrors = true;
    }

    public function addHashSynced(string $message, array $details = []): void
    {
        $this->events[] = new SyncEvent(type: SyncEventType::HashSynced, message: $message, details: $details);
        $this->hasChanges = true;
    }

    /** @return SyncEvent[] */
    public function getEventsByType(SyncEventType $type): array
    {
        return array_filter(array: $this->events, callback: fn(SyncEvent $e) => $e->type === $type);
    }

    public function hasErrors(): bool
    {
        return $this->hasErrors;
    }

    public function hasWarnings(): bool
    {
        return $this->hasWarnings;
    }

    public function hasChanges(): bool
    {
        return $this->hasChanges;
    }

    public function getExitCode(bool $validate = false): int
    {
        if ($this->hasErrors()) {
            return 2;
        }
        if ($this->hasWarnings()) {
            return 1;
        }
        if ($validate && $this->hasChanges()) {
            return 2;
        }
        return 0;
    }

    public function printSummary(): void
    {
        echo "\n=== STORY SYNC SUMMARY ===\n";

        foreach (SyncEventType::cases() as $type) {
            $events = $this->getEventsByType(type: $type);
            if (empty($events)) {
                continue;
            }

            echo strtoupper(string: "[$type->value]") . "\n";
            foreach ($events as $event) {
                echo "  $event->message\n";
                if (!empty($event->details['added'])) {
                    foreach ($event->details['added'] as $method) {
                        echo "    + $method\n";
                    }
                }
                if (!empty($event->details['provider_updated'])) {
                    foreach ($event->details['provider_updated'] as $provider) {
                        echo "    ~ $provider\n";
                    }
                }
                if (!empty($event->details['synced_hashes'])) {
                    foreach ($event->details['synced_hashes'] as $scenario) {
                        echo "    # $scenario\n";
                    }
                }
                if (!empty($event->details['preview'])) {
                    echo "\n  Preview of new methods:\n";
                    // Indent each line of the preview
                    $lines = explode(separator: "\n", string: $event->details['preview']);
                    foreach ($lines as $line) {
                        echo "  │ $line\n";
                    }
                }
            }
            echo "\n";
        }

        echo "=== END SUMMARY ===\n";
    }
}
