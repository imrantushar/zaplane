<?php

namespace Zaplane\Framework\Logging\Handlers;

use Zaplane\Framework\Logging\LogEntry;
use Zaplane\Framework\Logging\LogLevel;

if (!defined('ABSPATH')) exit;

/**
 * Writes log entries to PHP error_log.
 */
class ErrorLogHandler extends AbstractHandler
{
    protected string $prefix;

    public function __construct(
        string $minLevel = LogLevel::DEBUG,
        string $prefix = '[Zaplane]'
    ) {
        parent::__construct($minLevel);
        $this->prefix = $prefix;
    }

    /**
     * Handle a log entry.
     */
    public function handle(LogEntry $entry): bool
    {
        if (!$this->isHandling($entry->getLevel())) {
            return false;
        }

        $message = sprintf(
            '%s %s.%s: %s',
            $this->prefix,
            strtoupper($entry->getChannel()),
            strtoupper($entry->getLevel()),
            $entry->getInterpolatedMessage()
        );

        $context = $entry->getContext();
        if (!empty($context)) {
            $message .= ' ' . json_encode($context);
        }

        error_log($message);

        return true;
    }
}
