<?php

/**
 * Zaplane Helper Functions
 *
 * Provides convenient global functions for common operations.
 */

use Zaplane\Framework\Config\Config;
use Zaplane\Framework\Config\Repository;
use Zaplane\Framework\Logging\Logger;
use Zaplane\Framework\Logging\LogManager;

if (!defined('ABSPATH')) exit;

if (!function_exists('config')) {
    /**
     * Laravel-like config helper function.
     *
     * @param string|null $key The configuration key (dot notation supported)
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     *
     * Usage:
     *   config('app.name')           // Get a value
     *   config('app.debug', false)   // Get with default
     */
    function config(?string $key = null, $default = null)
    {
        $config = Config::getInstance();

        if ($key === null) {
            return $config;
        }

        return $config->get($key, $default);
    }
}

if (!function_exists('zaplane_config')) {
    /**
     * Get a configuration value or the Config instance.
     *
     * @param string|null $key The configuration key (dot notation supported)
     * @param mixed $default Default value if key doesn't exist
     * @return mixed|Config
     *
     * Usage:
     *   zaplane_config('app.debug')           // Get a value
     *   zaplane_config('app.debug', false)    // Get with default
     *   zaplane_config()                       // Get Config instance
     */
    function zaplane_config(?string $key = null, $default = null)
    {
        $config = Config::getInstance();

        if ($key === null) {
            return $config;
        }

        return $config->get($key, $default);
    }
}

if (!function_exists('zaplane_config_set')) {
    /**
     * Set a configuration value.
     *
     * @param string $key The configuration key
     * @param mixed $value The value to set
     * @return Config
     */
    function zaplane_config_set(string $key, $value): Config
    {
        return Config::getInstance()->set($key, $value);
    }
}

if (!function_exists('zaplane_config_repository')) {
    /**
     * Get a scoped configuration repository.
     *
     * @param string $namespace The namespace for the repository
     * @return Repository
     *
     * Usage:
     *   $logging = zaplane_config_repository('logging');
     *   $level = $logging->get('level');
     */
    function zaplane_config_repository(string $namespace): Repository
    {
        return new Repository(Config::getInstance(), $namespace);
    }
}

if (!function_exists('zaplane_logger')) {
    /**
     * Get the logger instance or log a message.
     *
     * @param string|null $message Optional message to log
     * @param array $context Optional context data
     * @param string $level Log level (default: info)
     * @return Logger|null
     *
     * Usage:
     *   zaplane_logger()                              // Get Logger instance
     *   zaplane_logger('User logged in')              // Log info message
     *   zaplane_logger('Error occurred', [], 'error') // Log error message
     */
    function zaplane_logger(?string $message = null, array $context = [], string $level = 'info'): ?Logger
    {
        $logger = Logger::getInstance();

        if ($message !== null) {
            $logger->log($level, $message, $context);
            return null;
        }

        return $logger;
    }
}

if (!function_exists('zaplane_log')) {
    /**
     * Log a message at a specific level.
     *
     * @param string $level The log level
     * @param string $message The message to log
     * @param array $context Optional context data
     */
    function zaplane_log(string $level, string $message, array $context = []): void
    {
        Logger::getInstance()->log($level, $message, $context);
    }
}

if (!function_exists('zaplane_log_debug')) {
    /**
     * Log a debug message.
     */
    function zaplane_log_debug(string $message, array $context = []): void
    {
        Logger::getInstance()->debug($message, $context);
    }
}

if (!function_exists('zaplane_log_info')) {
    /**
     * Log an info message.
     */
    function zaplane_log_info(string $message, array $context = []): void
    {
        Logger::getInstance()->info($message, $context);
    }
}

if (!function_exists('zaplane_log_warning')) {
    /**
     * Log a warning message.
     */
    function zaplane_log_warning(string $message, array $context = []): void
    {
        Logger::getInstance()->warning($message, $context);
    }
}

if (!function_exists('zaplane_log_error')) {
    /**
     * Log an error message.
     */
    function zaplane_log_error(string $message, array $context = []): void
    {
        Logger::getInstance()->error($message, $context);
    }
}

if (!function_exists('zaplane_log_exception')) {
    /**
     * Log an exception.
     */
    function zaplane_log_exception(\Throwable $exception, array $context = []): void
    {
        Logger::getInstance()->exception($exception, 'error', $context);
    }
}

if (!function_exists('zaplane_log_channel')) {
    /**
     * Get a logger for a specific channel.
     *
     * @param string $channel The channel name
     * @return Logger
     */
    function zaplane_log_channel(string $channel): Logger
    {
        return LogManager::getInstance()->channel($channel);
    }
}

if (!function_exists('zaplane_env')) {
    /**
     * Get the current environment.
     *
     * @return string 'production', 'development', 'staging', etc.
     */
    function zaplane_env(): string
    {
        return Config::getInstance()->getEnvironment();
    }
}

if (!function_exists('zaplane_is_debug')) {
    /**
     * Check if debug mode is enabled.
     *
     * @return bool
     */
    function zaplane_is_debug(): bool
    {
        return Config::getInstance()->get('app.debug', false);
    }
}
