<?php
namespace Zaplane\Classes;

if (!defined('ABSPATH')) exit;

class Logger {

    const ENABLED = true; // Set false to disable all logging

    private static int $step = 0;
    private static array $logs = [];

    /**
     * Log a step with context
     */
    public static function log(string $message, array $context = []): void {
        if (! ZAPLANE_ALLOW_LOGS ) return;

        self::$step++;
        $entry = [
            'step' => self::$step,
            'message' => $message,
            'context' => $context,
            'time' => current_time('mysql')
        ];

        // Store in memory
        self::$logs[] = $entry;

        // Optionally also log to WP debug log
        error_log("[Zaplane Step ".self::$step."] ".$message." ".json_encode($context));
    }

    /**
     * Get all logged steps
     */
    public static function get_logs(): array {
        return self::$logs;
    }

    /**
     * Reset steps (call at start of workflow)
     */
    public static function reset(): void {
        self::$step = 0;
        self::$logs = [];
    }
}
