<?php
namespace Zaplane\Framework\Core;

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Framework\Classes\BaseAction;
use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

/**
 * Integration Loader
 *
 * Manages registration and lazy loading of integrations.
 * Supports both legacy single-file integrations and new modular integrations.
 *
 * Legacy: integrations/slack.php (all code in one file)
 * Modular: integrations/slack/SlackIntegration.php + /actions/*.php + /triggers/*.php
 */
class IntegrationLoader {

    protected static array $registry = [];
    protected static array $instances = [];
    protected static bool $initialized = false;

    /**
     * Cache of discovered modular triggers.
     * Structure: ['slug' => ['trigger_key' => TriggerClassName, ...], ...]
     */
    protected static array $modular_triggers = [];

    /**
     * Cache of discovered modular actions.
     * Structure: ['slug' => ['action_key' => ActionClassName, ...], ...]
     */
    protected static array $modular_actions = [];

    /**
     * Initialize the integration registry
     * Called automatically on first use - no need to call manually
     *
     * Loads from:
     *  1. Manual registry file (integration-registry.php)
     *  2. Auto-discovered legacy integrations from integrations/*.php
     *  3. Auto-discovered modular integrations from integrations/[name]/ directories
     *  4. Third-party integrations via `zaplane_register_integrations_registry` hook
     */
    protected static function ensureInitialized(): void {
        if (self::$initialized) {
            return;
        }

        // Load manual registry
        self::$registry = require ZAPLANE_INCLUDES_DIR_PATH . 'framework/core/integration-registry.php';

        // Auto-discover legacy integrations from integrations/*.php files
        self::autoDiscover();

        // Auto-discover modular integrations from integrations/[name]/ directories
        self::autoDiscoverModular();

        // Allow third-party plugins to register integrations
        do_action('zaplane_register_integrations_registry', self::$registry);
        self::$initialized = true;
    }

    /**
     * Auto-discover legacy integration classes from integrations/*.php files.
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
            if ( ! is_subclass_of( $fqcn, IntegrationBase::class ) ) {
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
     * Auto-discover modular integrations from integrations/[name]/ directories.
     *
     * Modular structure (kebab-case file names):
     *   integrations/slack/
     *     slack-integration.php  -> Zaplane\Integrations\Slack\SlackIntegration
     *     actions/
     *       send-message.php     -> Zaplane\Integrations\Slack\Actions\SendMessage
     *     triggers/
     *       message-received.php -> Zaplane\Integrations\Slack\Triggers\MessageReceived
     */
    protected static function autoDiscoverModular(): void {
        $base_dir = ZAPLANE_ROOT_DIR_PATH . 'integrations/';
        if ( ! is_dir( $base_dir ) ) {
            return;
        }

        $dirs = glob( $base_dir . '*', GLOB_ONLYDIR );
        if ( ! $dirs ) {
            return;
        }

        foreach ( $dirs as $integration_dir ) {
            $slug = strtolower( basename( $integration_dir ) );

            // Look for the main integration file in kebab-case (e.g., slack-integration.php)
            $kebab_file = $slug . '-integration.php';
            $file       = $integration_dir . '/' . $kebab_file;

            if ( ! file_exists( $file ) ) {
                continue;
            }

            // Class name is PascalCase: SlackIntegration
            $class_name = ucfirst( $slug ) . 'Integration';
            $fqcn       = 'Zaplane\\Integrations\\' . ucfirst( $slug ) . '\\' . $class_name;

            // Load the class if not already loaded
            if ( ! class_exists( $fqcn ) ) {
                require_once $file;
            }

            if ( ! class_exists( $fqcn ) ) {
                continue;
            }

            // Verify it extends IntegrationBase
            if ( ! is_subclass_of( $fqcn, IntegrationBase::class ) ) {
                continue;
            }

            // Register the modular integration (may override legacy)
            self::$registry[ $slug ] = [
                'file'    => $slug . '/' . $kebab_file,
                'class'   => $fqcn,
                'modular' => true,
            ];

            // Discover triggers and actions for this modular integration
            self::discoverTriggersAndActions( $slug, $integration_dir );
        }
    }

    /**
     * Convert kebab-case filename to PascalCase class name.
     *
     * @param string $kebab Kebab-case string (e.g., 'send-message')
     * @return string PascalCase string (e.g., 'SendMessage')
     */
    protected static function kebabToPascal( string $kebab ): string {
        return str_replace( ' ', '', ucwords( str_replace( '-', ' ', $kebab ) ) );
    }

    /**
     * Discover trigger and action classes from a modular integration directory.
     *
     * File names use kebab-case (e.g., send-message.php)
     * Class names use PascalCase (e.g., SendMessage)
     *
     * @param string $slug            Integration slug (lowercase)
     * @param string $integration_dir Full path to the integration directory
     */
    protected static function discoverTriggersAndActions( string $slug, string $integration_dir ): void {
        // Initialize arrays for this integration
        if ( ! isset( self::$modular_triggers[ $slug ] ) ) {
            self::$modular_triggers[ $slug ] = [];
        }
        if ( ! isset( self::$modular_actions[ $slug ] ) ) {
            self::$modular_actions[ $slug ] = [];
        }

        // Discover triggers
        $triggers_dir = $integration_dir . '/triggers/';
        if ( is_dir( $triggers_dir ) ) {
            $trigger_files = glob( $triggers_dir . '*.php' );
            foreach ( $trigger_files ?: [] as $file ) {
                // Convert kebab-case filename to PascalCase class name
                $kebab_name = basename( $file, '.php' );
                $class_name = self::kebabToPascal( $kebab_name );
                $fqcn       = 'Zaplane\\Integrations\\' . ucfirst( $slug ) . '\\Triggers\\' . $class_name;

                if ( ! class_exists( $fqcn ) ) {
                    require_once $file;
                }

                if ( class_exists( $fqcn ) && is_subclass_of( $fqcn, BaseTrigger::class ) ) {
                    $key = $fqcn::get_key();
                    self::$modular_triggers[ $slug ][ $key ] = $fqcn;
                }
            }
        }

        // Discover actions
        $actions_dir = $integration_dir . '/actions/';
        if ( is_dir( $actions_dir ) ) {
            $action_files = glob( $actions_dir . '*.php' );
            foreach ( $action_files ?: [] as $file ) {
                // Convert kebab-case filename to PascalCase class name
                $kebab_name = basename( $file, '.php' );
                $class_name = self::kebabToPascal( $kebab_name );
                $fqcn       = 'Zaplane\\Integrations\\' . ucfirst( $slug ) . '\\Actions\\' . $class_name;

                if ( ! class_exists( $fqcn ) ) {
                    require_once $file;
                }

                if ( class_exists( $fqcn ) && is_subclass_of( $fqcn, BaseAction::class ) ) {
                    $key = $fqcn::get_key();
                    self::$modular_actions[ $slug ][ $key ] = $fqcn;
                }
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
     * Get all triggers for an integration.
     *
     * Combines modular triggers (from /triggers/*.php files) with legacy
     * triggers (from get_triggers() method). Modular triggers take precedence.
     *
     * @param string $slug Integration slug
     * @return array Trigger definitions keyed by trigger key
     */
    public static function getTriggers( string $slug ): array {
        self::ensureInitialized();
        $slug = strtolower( $slug );

        $triggers = [];

        // 1. Get modular triggers first
        if ( ! empty( self::$modular_triggers[ $slug ] ) ) {
            foreach ( self::$modular_triggers[ $slug ] as $key => $class ) {
                $triggers[ $key ] = $class::build();
            }
        }

        // 2. Merge with legacy triggers (modular takes precedence)
        $integration = self::get( $slug );
        if ( $integration ) {
            $class         = get_class( $integration );
            $legacy        = $class::get_triggers();
            foreach ( $legacy as $key => $def ) {
                if ( ! isset( $triggers[ $key ] ) ) {
                    $triggers[ $key ] = $def;
                }
            }
        }

        return $triggers;
    }

    /**
     * Get all actions for an integration.
     *
     * Combines modular actions (from /actions/*.php files) with legacy
     * actions (from get_actions() method). Modular actions take precedence.
     *
     * @param string $slug Integration slug
     * @return array Action definitions keyed by action key
     */
    public static function getActions( string $slug ): array {
        self::ensureInitialized();
        $slug = strtolower( $slug );

        $actions = [];

        // 1. Get modular actions first
        if ( ! empty( self::$modular_actions[ $slug ] ) ) {
            foreach ( self::$modular_actions[ $slug ] as $key => $class ) {
                $actions[ $key ] = $class::build();
            }
        }

        // 2. Merge with legacy actions (modular takes precedence)
        $integration = self::get( $slug );
        if ( $integration ) {
            $class       = get_class( $integration );
            $legacy      = $class::get_actions();
            foreach ( $legacy as $key => $def ) {
                if ( ! isset( $actions[ $key ] ) ) {
                    $actions[ $key ] = $def;
                }
            }
        }

        return $actions;
    }

    /**
     * Get a specific trigger class for modular triggers.
     *
     * Returns null for legacy triggers (they use the integration class directly).
     *
     * @param string $slug       Integration slug
     * @param string $trigger_key Trigger key
     * @return string|null Fully qualified trigger class name, or null
     */
    public static function getTriggerClass( string $slug, string $trigger_key ): ?string {
        self::ensureInitialized();
        $slug = strtolower( $slug );

        return self::$modular_triggers[ $slug ][ $trigger_key ] ?? null;
    }

    /**
     * Get a specific action class for modular actions.
     *
     * Returns null for legacy actions (they use the integration class directly).
     *
     * @param string $slug       Integration slug
     * @param string $action_key Action key
     * @return string|null Fully qualified action class name, or null
     */
    public static function getActionClass( string $slug, string $action_key ): ?string {
        self::ensureInitialized();
        $slug = strtolower( $slug );

        return self::$modular_actions[ $slug ][ $action_key ] ?? null;
    }

    /**
     * Check if an integration uses modular structure.
     *
     * @param string $slug Integration slug
     * @return bool
     */
    public static function isModular( string $slug ): bool {
        self::ensureInitialized();
        $slug = strtolower( $slug );

        return ! empty( self::$registry[ $slug ]['modular'] );
    }

    /**
     * Get trigger config schema for an integration.
     *
     * For modular triggers, returns from the trigger class.
     * For legacy triggers, calls integration's get_trigger_config_schema().
     *
     * @param string $slug        Integration slug
     * @param string $trigger_key Trigger key
     * @return array Config schema
     */
    public static function getTriggerConfigSchema( string $slug, string $trigger_key ): array {
        self::ensureInitialized();
        $slug = strtolower( $slug );

        // Check modular first
        $trigger_class = self::getTriggerClass( $slug, $trigger_key );
        if ( $trigger_class ) {
            return $trigger_class::get_config_schema();
        }

        // Fall back to legacy
        $integration = self::get( $slug );
        if ( $integration ) {
            $class = get_class( $integration );
            return $class::get_trigger_config_schema( $trigger_key );
        }

        return [];
    }

    /**
     * Get action config schema for an integration.
     *
     * For modular actions, returns from the action class.
     * For legacy actions, calls integration's get_action_config_schema().
     *
     * @param string $slug       Integration slug
     * @param string $action_key Action key
     * @return array Config schema
     */
    public static function getActionConfigSchema( string $slug, string $action_key ): array {
        self::ensureInitialized();
        $slug = strtolower( $slug );

        // Check modular first
        $action_class = self::getActionClass( $slug, $action_key );
        if ( $action_class ) {
            return $action_class::get_config_schema();
        }

        // Fall back to legacy
        $integration = self::get( $slug );
        if ( $integration ) {
            $class = get_class( $integration );
            return $class::get_action_config_schema( $action_key );
        }

        return [];
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
