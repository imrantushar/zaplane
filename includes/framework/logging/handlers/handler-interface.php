<?php

namespace Zaplane\Framework\Logging\Handlers;

use Zaplane\Framework\Logging\LogEntry;

if (!defined('ABSPATH')) exit;

/**
 * Interface for log handlers.
 */
interface HandlerInterface
{
    /**
     * Handle a log entry.
     */
    public function handle(LogEntry $entry): bool;

    /**
     * Check if this handler handles a given level.
     */
    public function isHandling(string $level): bool;

    /**
     * Close the handler.
     */
    public function close(): void;
}
