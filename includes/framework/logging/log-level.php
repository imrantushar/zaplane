<?php

namespace Zaplane\Framework\Logging;

if (!defined('ABSPATH')) exit;

/**
 * PSR-3 Log Levels
 */
class LogLevel
{
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';

    /**
     * Log level priority (higher = more severe).
     */
    public const LEVELS = [
        self::DEBUG => 0,
        self::INFO => 1,
        self::NOTICE => 2,
        self::WARNING => 3,
        self::ERROR => 4,
        self::CRITICAL => 5,
        self::ALERT => 6,
        self::EMERGENCY => 7,
    ];

    /**
     * Get all log levels.
     */
    public static function all(): array
    {
        return array_keys(self::LEVELS);
    }

    /**
     * Check if a level is valid.
     */
    public static function isValid(string $level): bool
    {
        return isset(self::LEVELS[$level]);
    }

    /**
     * Get the priority of a level.
     */
    public static function priority(string $level): int
    {
        return self::LEVELS[$level] ?? 0;
    }

    /**
     * Check if level meets minimum threshold.
     */
    public static function meetsThreshold(string $level, string $threshold): bool
    {
        return self::priority($level) >= self::priority($threshold);
    }
}
