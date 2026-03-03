<?php
namespace Zaplane\Framework\Core;

if (!defined('ABSPATH')) exit;

/**
 * Integration Loader
 *
 * Manages registration and lazy loading of integrations.
 * Uses automatic initialization - no need to call init() manually.
 */
class IntegrationLoader {

    protected static array $registry = [];
    protected static array $instances = [];
    protected static bool $initialized = false;

    /**
     * Initialize the integration registry
     * Called automatically on first use - no need to call manually
     */
    protected static function ensureInitialized(): void {
        if (self::$initialized) {
            return;
        }

        self::$registry = zaplane_config('integrations.registry', []);
        self::$registry = apply_filters('zaplane_integrations', self::$registry);
        self::$initialized = true;
    }

    /**
     * Get an integration instance by slug
     * Automatically initializes registry if needed
     *
     * @param string $slug Integration slug (e.g., 'slack', 'wordpress')
     * @return object|null Integration instance or null if not found
     */
    public static function get(string $slug): ?object {
        self::ensureInitialized();

        // Return cached instance if exists
        if (!empty(self::$instances[$slug])) {
            return self::$instances[$slug];
        }

        // Check if integration is registered
        if (empty(self::$registry[$slug])) {
            return null;
        }

        // Load integration file
        $meta  = self::$registry[$slug];
        $file  = (!empty($meta['path']))
            ? $meta['path']
            : ZAPLANE_INTEGRATION_DIR_PATH . '/' . basename($meta['file']);
        $class = $meta['class'];

        if (!class_exists($class) && file_exists($file)) {
            require_once $file;
        }

        if (!class_exists($class)) {
            return null;
        }

        // Create and cache instance
        $instance = new $class();
        self::$instances[$slug] = $instance;

        return $instance;
    }

    /**
     * Get all registered integration slugs (without loading instances)
     * Automatically initializes registry if needed
     *
     * @return array Array of integration slugs
     */
    public static function getAllSlugs(): array {
        self::ensureInitialized();
        return array_keys(self::$registry);
    }

    /**
     * Get registry metadata without loading instances
     * Automatically initializes registry if needed
     *
     * @return array Registry metadata
     */
    public static function getRegistry(): array {
        self::ensureInitialized();
        return self::$registry;
    }

    /**
     * Load ALL integration instances
     * Only used for WP-CLI JSON generator
     * Most code should use get() or getRegistry() instead
     *
     * @return array All integration instances keyed by slug
     */
    public static function all(): array {
        self::ensureInitialized();

        $all = [];
        foreach (self::$registry as $slug => $meta) {
            $instance = self::get($slug);
            if ($instance) {
                $all[$slug] = $instance;
            }
        }
        return $all;
    }

    /**
     * Check if an integration is registered
     * Automatically initializes registry if needed
     *
     * @param string $slug Integration slug
     * @return bool True if integration exists
     */
    public static function has(string $slug): bool {
        self::ensureInitialized();
        return isset(self::$registry[$slug]);
    }

    /**
     * For container compatibility - returns self
     * Container calls this during boot, but automatic initialization
     * means it's not actually needed anymore
     *
     * @deprecated Use static methods directly instead
     */
    public static function init(): self {
        self::ensureInitialized();
        return new self();
    }
}
