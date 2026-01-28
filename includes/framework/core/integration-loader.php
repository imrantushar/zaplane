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
     *
     * Loads from:
     *  1. Manual registry file (integration-registry.php)
     *  2. Auto-discovered integrations from the integrations/ directory
     *  3. Third-party integrations via `zaplane_register_integrations_registry` hook
     */
    protected static function ensureInitialized(): void {
        if (self::$initialized) {
            return;
        }

        // Load manual registry
        self::$registry = require ZAPLANE_INCLUDES_DIR_PATH . 'framework/core/integration-registry.php';

        // Auto-discover integrations from the integrations/ directory
        self::autoDiscover();

        // Allow third-party plugins to register integrations
        do_action('zaplane_register_integrations_registry', self::$registry);
        self::$initialized = true;
    }

    /**
     * Auto-discover integration classes from the integrations/ directory.
     *
     * Scans for PHP files, loads them to check if they contain a class
     * extending IntegrationBase, and registers them if not already in the registry.
     */
    protected static function autoDiscover(): void {
        $dir = ZAPLANE_ROOT_DIR_PATH . 'integrations/';
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $files = glob( $dir . '*.php' );
        if ( ! $files ) {
            return;
        }

        foreach ( $files as $file ) {
            $basename = basename( $file, '.php' );

            // Convert kebab-case filename to PascalCase class name
            $class_name = str_replace( ' ', '', ucwords( str_replace( '-', ' ', $basename ) ) );
            $fqcn       = 'Zaplane\\Integrations\\' . $class_name;

            // Skip if already registered by manual registry
            foreach ( self::$registry as $slug => $meta ) {
                if ( ( $meta['class'] ?? '' ) === $fqcn ) {
                    continue 2;
                }
            }

            // Try to load and validate the class
            if ( ! class_exists( $fqcn ) && file_exists( $file ) ) {
                require_once $file;
            }

            if ( ! class_exists( $fqcn ) ) {
                continue;
            }

            // Verify it extends IntegrationBase
            if ( ! is_subclass_of( $fqcn, \Zaplane\Framework\Classes\IntegrationBase::class ) ) {
                continue;
            }

            $slug = $fqcn::get_slug();
            if ( ! isset( self::$registry[ $slug ] ) ) {
                self::$registry[ $slug ] = [
                    'file'  => $basename . '.php',
                    'class' => $fqcn,
                ];
            }
        }
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

        // Load integration file — check both integrations/ dirs
        $file = ZAPLANE_ROOT_DIR_PATH . 'integrations/' . basename(self::$registry[$slug]['file']);
        if ( ! file_exists( $file ) ) {
            $file = ZAPLANE_INTEGRATION_DIR_PATH . '/' . basename(self::$registry[$slug]['file']);
        }
        $class = self::$registry[$slug]['class'];

        if (!class_exists($class) && file_exists($file)) {
            require_once $file;
        }

        if (!class_exists($class)) {
            return null;
        }

        // Validate integration on first load
        self::validateIntegration($slug, $class);

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

    /**
     * Validate an integration class on first load.
     *
     * Checks for common issues and logs warnings. Does NOT block loading —
     * integration still works, but developers get feedback in the error log.
     *
     * @param string $slug  Integration slug.
     * @param string $class Fully qualified class name.
     */
    protected static function validateIntegration(string $slug, string $class): void {
        // Only validate in debug mode to avoid production overhead
        if ( ! defined('WP_DEBUG') || ! WP_DEBUG ) {
            return;
        }

        $warnings = [];

        // Check slug consistency
        if ( method_exists( $class, 'get_slug' ) && $class::get_slug() !== $slug ) {
            $warnings[] = "get_slug() returns '{$class::get_slug()}' but registered as '{$slug}'";
        }

        // If requires_connection, verify auth setup
        if ( $class::requires_connection() ) {
            if ( $class::get_auth_type() === 'none' ) {
                $warnings[] = "requires_connection() is true but get_auth_type() returns 'none'";
            }
            if ( empty( $class::get_auth_fields() ) ) {
                $warnings[] = "requires_connection() is true but get_auth_fields() is empty";
            }
        }

        // Check triggers have hooks
        foreach ( $class::get_triggers() as $key => $trigger ) {
            if ( empty( $trigger['hook'] ) ) {
                $warnings[] = "Trigger '{$key}' is missing 'hook' field";
            }
        }

        if ( ! empty( $warnings ) ) {
            error_log( sprintf(
                'Zaplane integration validation [%s]: %s',
                $slug,
                implode( '; ', $warnings )
            ) );
        }
    }
}
