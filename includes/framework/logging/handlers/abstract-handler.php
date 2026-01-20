<?php

namespace Zaplane\Framework\Logging\Handlers;

use Zaplane\Framework\Logging\LogEntry;
use Zaplane\Framework\Logging\LogLevel;

if (!defined('ABSPATH')) exit;

/**
 * Base handler class with common functionality.
 */
abstract class AbstractHandler implements HandlerInterface
{
    protected string $minLevel;
    protected string $format = '[{timestamp}] {channel}.{level}: {message} {context}';
    protected bool $bubble = true;

    public function __construct(string $minLevel = LogLevel::DEBUG)
    {
        $this->minLevel = $minLevel;
    }

    /**
     * Check if this handler handles a given level.
     */
    public function isHandling(string $level): bool
    {
        return LogLevel::meetsThreshold($level, $this->minLevel);
    }

    /**
     * Set the minimum log level.
     */
    public function setMinLevel(string $level): self
    {
        $this->minLevel = $level;
        return $this;
    }

    /**
     * Set the log format.
     */
    public function setFormat(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Set whether to bubble to next handler.
     */
    public function setBubble(bool $bubble): self
    {
        $this->bubble = $bubble;
        return $this;
    }

    /**
     * Format a log entry.
     */
    protected function formatEntry(LogEntry $entry): string
    {
        return $entry->format($this->format);
    }

    /**
     * Close the handler.
     */
    public function close(): void
    {
        // Override in subclasses if needed
    }
}
