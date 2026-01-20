<?php

namespace Zaplane\Framework\Logging\Handlers;

use Zaplane\Framework\Logging\LogEntry;
use Zaplane\Framework\Logging\LogLevel;

if (!defined('ABSPATH')) exit;

/**
 * Stores log entries in memory.
 * Useful for debugging and testing.
 */
class MemoryHandler extends AbstractHandler
{
    protected array $entries = [];
    protected int $maxEntries;

    public function __construct(
        string $minLevel = LogLevel::DEBUG,
        int $maxEntries = 1000
    ) {
        parent::__construct($minLevel);
        $this->maxEntries = $maxEntries;
    }

    /**
     * Handle a log entry.
     */
    public function handle(LogEntry $entry): bool
    {
        if (!$this->isHandling($entry->getLevel())) {
            return false;
        }

        $this->entries[] = $entry;

        // Trim if exceeding max entries
        if (count($this->entries) > $this->maxEntries) {
            $this->entries = array_slice($this->entries, -$this->maxEntries);
        }

        return true;
    }

    /**
     * Get all stored entries.
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * Get entries filtered by level.
     */
    public function getEntriesByLevel(string $level): array
    {
        return array_filter($this->entries, fn($entry) => $entry->getLevel() === $level);
    }

    /**
     * Get entries filtered by channel.
     */
    public function getEntriesByChannel(string $channel): array
    {
        return array_filter($this->entries, fn($entry) => $entry->getChannel() === $channel);
    }

    /**
     * Get entries filtered by trace ID.
     */
    public function getEntriesByTraceId(string $traceId): array
    {
        return array_filter($this->entries, fn($entry) => $entry->getTraceId() === $traceId);
    }

    /**
     * Search entries by message.
     */
    public function search(string $query): array
    {
        return array_filter($this->entries, function ($entry) use ($query) {
            return stripos($entry->getMessage(), $query) !== false ||
                   stripos($entry->getInterpolatedMessage(), $query) !== false;
        });
    }

    /**
     * Clear all entries.
     */
    public function clear(): void
    {
        $this->entries = [];
    }

    /**
     * Get entry count.
     */
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Get the last entry.
     */
    public function last(): ?LogEntry
    {
        return $this->entries[array_key_last($this->entries)] ?? null;
    }

    /**
     * Check if any errors were logged.
     */
    public function hasErrors(): bool
    {
        foreach ($this->entries as $entry) {
            if (LogLevel::meetsThreshold($entry->getLevel(), LogLevel::ERROR)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Convert all entries to array.
     */
    public function toArray(): array
    {
        return array_map(fn($entry) => $entry->toArray(), $this->entries);
    }
}
