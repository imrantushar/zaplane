<?php
namespace Zaplane;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Autoload {

    /**
     * Instance
     */
    private static ?self $instance = null;

    /**
     * Namespace => directories map (supports multiple directories per namespace)
     */
    private array $autoload_directories = [];

    /**
     * Get singleton instance
     */
    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add namespace directory mapping
     */
    public function add_namespace_directory(string $namespace, string $directory): void {
        $ns = rtrim($namespace, '\\');
        $dir = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!isset($this->autoload_directories[$ns])) {
            $this->autoload_directories[$ns] = [];
        }

        $this->autoload_directories[$ns][] = $dir;
    }

    /**
     * Autoload callback
     */
    public function autoload(string $class): void {
        foreach ($this->autoload_directories as $namespace => $directories) {
            if (0 !== strpos($class, $namespace)) {
                continue;
            }

            // Remove namespace prefix
            $relative_class = substr($class, strlen($namespace) + 1);

            // Convert namespace to folder structure + kebab-case file
            $relative_path = strtolower(
                preg_replace(
                    ['/([a-z])([A-Z])/', '/_/', '/\\\/'],
                    ['$1-$2', '-', DIRECTORY_SEPARATOR],
                    $relative_class
                )
            ) . '.php';

            foreach ($directories as $directory) {
                $file = $directory . $relative_path;
                if (is_readable($file)) {
                    require_once $file;
                    return;
                }
            }
        }
    }x

    /**
     * Constructor: register autoloader
     */
    private function __construct() {
        // Register autoload callback
        spl_autoload_register([$this, 'autoload']);

        // Default namespace mappings
        $this->add_namespace_directory('Zaplane', ZAPLANE_ROOT_DIR_PATH . 'includes/');
        $this->add_namespace_directory('Zaplane\\Integration', ZAPLANE_ROOT_DIR_PATH . 'integrations/');
        $this->add_namespace_directory('Zaplane\\Integration', ZAPLANE_ROOT_DIR_PATH . 'integration/');

        // Framework namespace mappings (core system - like vendor/)
        $this->add_namespace_directory('Zaplane\\Framework\\Database\\ORM', ZAPLANE_ROOT_DIR_PATH . 'includes/framework/database/orm/');
        $this->add_namespace_directory('Zaplane\\Framework\\Console', ZAPLANE_ROOT_DIR_PATH . 'includes/framework/console/');
        $this->add_namespace_directory('Zaplane\\Framework\\Console\\Commands', ZAPLANE_ROOT_DIR_PATH . 'includes/framework/console/commands/');
        $this->add_namespace_directory('Zaplane\\Framework\\Config', ZAPLANE_ROOT_DIR_PATH . 'includes/framework/config/');
        $this->add_namespace_directory('Zaplane\\Framework\\Logging', ZAPLANE_ROOT_DIR_PATH . 'includes/framework/logging/');
        $this->add_namespace_directory('Zaplane\\Framework\\Logging\\Handlers', ZAPLANE_ROOT_DIR_PATH . 'includes/framework/logging/handlers/');
        $this->add_namespace_directory('Zaplane\\Framework\\Core', ZAPLANE_ROOT_DIR_PATH . 'includes/framework/core/');
        $this->add_namespace_directory('Zaplane\\Framework\\Classes', ZAPLANE_ROOT_DIR_PATH . 'includes/framework/classes/');
        $this->add_namespace_directory('Zaplane\\Framework\\Exceptions', ZAPLANE_ROOT_DIR_PATH . 'includes/framework/exceptions/');
        $this->add_namespace_directory('Zaplane\\Framework\\Models\\WordPress', ZAPLANE_ROOT_DIR_PATH . 'includes/framework/models/wordpress/');

        // Application namespace mappings (where developers work)
        $this->add_namespace_directory('Zaplane\\Models', ZAPLANE_ROOT_DIR_PATH . 'includes/models/');
        $this->add_namespace_directory('Zaplane\\Commands', ZAPLANE_ROOT_DIR_PATH . 'includes/commands/');
        $this->add_namespace_directory('Zaplane\\API', ZAPLANE_ROOT_DIR_PATH . 'includes/api/');
        $this->add_namespace_directory('Zaplane\\Ajax', ZAPLANE_ROOT_DIR_PATH . 'includes/ajax/');
        $this->add_namespace_directory('Zaplane\\Admin', ZAPLANE_ROOT_DIR_PATH . 'includes/admin/');
        $this->add_namespace_directory('Zaplane\\Database\\Migrations', ZAPLANE_ROOT_DIR_PATH . 'includes/database/migrations/');
    }
}

// Initialize autoloader
Autoload::get_instance();
