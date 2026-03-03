<?php

namespace Zaplane\Framework\Config;

use ArrayAccess;

if (!defined('ABSPATH')) exit;

/**
 * Configuration Manager
 *
 * Provides a fluent interface for accessing configuration values with dot notation support.
 * Supports multiple configuration sources: files, WordPress options, and runtime values.
 *
 * Usage:
 *   $config = Config::getInstance();
 *   $value = $config->get('app.debug', false);
 *   $config->set('app.name', 'Zaplane');
 */
class Config implements ArrayAccess
{
    private static ?self $instance = null;

    /**
     * All configuration items.
     */
    protected array $items = [];

    /**
     * Configuration file paths that have been loaded.
     */
    protected array $loadedFiles = [];

    /**
     * WordPress option prefix for persistent storage.
     */
    protected string $optionPrefix = 'zaplane_config_';

    /**
     * Get singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset instance (useful for testing).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    private function __construct()
    {
        $this->loadDefaults();
        $this->loadConfigFiles();
    }

    /**
     * Load configuration files from the config directory.
     */
    protected function loadConfigFiles(): void
    {
        if (!defined('ZAPLANE_INCLUDES_DIR_PATH')) {
            return;
        }

        $configPath = ZAPLANE_INCLUDES_DIR_PATH . 'config';
        $this->loadDirectory($configPath);
    }

    /**
     * Load default configuration.
     */
    protected function loadDefaults(): void
    {
        $this->items = [
            'app' => [
                'name' => 'Zaplane',
                'version' => defined('ZAPLANE_VERSION') ? ZAPLANE_VERSION : '1.0.0',
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
                'timezone' => function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC',
            ],
            'logging' => [
                'enabled' => defined('ZAPLANE_ALLOW_LOGS') && ZAPLANE_ALLOW_LOGS,
                'level' => 'debug',
                'channel' => 'file',
                'channels' => [
                    'file' => [
                        'driver' => 'file',
                        'path' => WP_CONTENT_DIR . '/zaplane-logs',
                        'days' => 14,
                    ],
                    'database' => [
                        'driver' => 'database',
                        'table' => 'zaplane_logs',
                        'days' => 30,
                    ],
                    'errorlog' => [
                        'driver' => 'errorlog',
                    ],
                ],
            ],
            'database' => [
                'prefix' => 'zaplane_',
                'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
                'collate' => defined('DB_COLLATE') ? DB_COLLATE : 'utf8mb4_unicode_ci',
            ],
            'cache' => [
                'enabled' => true,
                'driver' => 'transient',
                'prefix' => 'zaplane_cache_',
                'ttl' => 3600,
            ],

            'integrations' => [
                'auto_discover' => true,
                'cache_enabled' => true,
            ],
            'api' => [
                'namespace' => 'zaplane/v1',
                'rate_limit' => 100,
                'rate_limit_window' => 60,
            ],
        ];
    }

    /**
     * Load configuration from a PHP file.
     */
    public function loadFile(string $path, ?string $namespace = null): self
    {
        if (!file_exists($path)) {
            return $this;
        }

        if (in_array($path, $this->loadedFiles)) {
            return $this;
        }

        try {
            $config = require $path;
        } catch (\Throwable $e) {
            // Silently fail if config file has errors (e.g., undefined constants during tests)
            return $this;
        }

        if (!is_array($config)) {
            return $this;
        }

        $this->loadedFiles[] = $path;

        if ($namespace) {
            $this->set($namespace, array_merge(
                $this->get($namespace, []),
                $config
            ));
        } else {
            $this->items = array_replace_recursive($this->items, $config);
        }

        return $this;
    }

    /**
     * Load configuration from a directory.
     */
    public function loadDirectory(string $directory): self
    {
        if (!is_dir($directory)) {
            return $this;
        }

        $files = glob($directory . '/*.php');

        foreach ($files as $file) {
            $namespace = pathinfo($file, PATHINFO_FILENAME);
            $this->loadFile($file, $namespace);
        }

        return $this;
    }

    /**
     * Load configuration from WordPress options.
     */
    public function loadFromOptions(string $optionName): self
    {
        $value = get_option($optionName);

        if ($value === false) {
            return $this;
        }

        $config = is_string($value) ? json_decode($value, true) : $value;

        if (is_array($config)) {
            $this->items = array_replace_recursive($this->items, $config);
        }

        return $this;
    }

    /**
     * Get a configuration value using dot notation.
     *
     * @param string $key The configuration key (e.g., 'app.debug')
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->items;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a configuration value using dot notation.
     *
     * @param string $key The configuration key
     * @param mixed $value The value to set
     */
    public function set(string $key, $value): self
    {
        $keys = explode('.', $key);
        $current = &$this->items;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }

        return $this;
    }

    /**
     * Check if a configuration key exists.
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->items;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Remove a configuration key.
     */
    public function forget(string $key): self
    {
        $keys = explode('.', $key);
        $current = &$this->items;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                unset($current[$segment]);
                return $this;
            }

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return $this;
            }

            $current = &$current[$segment];
        }

        return $this;
    }

    /**
     * Get all configuration items.
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Merge configuration values.
     */
    public function merge(array $config): self
    {
        $this->items = array_replace_recursive($this->items, $config);
        return $this;
    }

    /**
     * Push a value onto an array configuration value.
     */
    public function push(string $key, $value): self
    {
        $array = $this->get($key, []);

        if (!is_array($array)) {
            $array = [];
        }

        $array[] = $value;

        return $this->set($key, $array);
    }

    /**
     * Prepend a value to an array configuration value.
     */
    public function prepend(string $key, $value): self
    {
        $array = $this->get($key, []);

        if (!is_array($array)) {
            $array = [];
        }

        array_unshift($array, $value);

        return $this->set($key, $array);
    }

    /**
     * Save configuration to WordPress options.
     */
    public function saveToOption(string $optionName, ?string $key = null): bool
    {
        $data = $key ? $this->get($key) : $this->items;
        return update_option($optionName, json_encode($data));
    }

    /**
     * Get configuration for a specific environment.
     */
    public function environment(string $env, string $key, $default = null)
    {
        $envKey = "{$key}.{$env}";

        if ($this->has($envKey)) {
            return $this->get($envKey);
        }

        return $this->get($key, $default);
    }

    /**
     * Get the current environment.
     */
    public function getEnvironment(): string
    {
        if (defined('WP_ENVIRONMENT_TYPE')) {
            return WP_ENVIRONMENT_TYPE;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            return 'development';
        }

        return 'production';
    }

    /**
     * Check if running in a specific environment.
     */
    public function isEnvironment(string $env): bool
    {
        return $this->getEnvironment() === $env;
    }

    // ArrayAccess implementation
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->forget($offset);
    }
}
