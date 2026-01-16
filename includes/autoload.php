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
     * Namespace => directory map
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
        $this->autoload_directories[rtrim($namespace, '\\')] = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Autoload callback
     */
    public function autoload(string $class): void {
        foreach ($this->autoload_directories as $namespace => $directory) {
            if (0 !== strpos($class, $namespace)) {
                continue;
            }

            // Remove namespace prefix
            $relative_class = substr($class, strlen($namespace) + 1);

            // Convert namespace to folder structure + kebab-case file
            $file = $directory . strtolower(
                preg_replace(
                    ['/([a-z])([A-Z])/', '/_/', '/\\\/'],
                    ['$1-$2', '-', DIRECTORY_SEPARATOR],
                    $relative_class
                )
            ) . '.php';

            if (is_readable($file)) {
                require_once $file;
            }
        }
    }

    /**
     * Constructor: register autoloader
     */
    private function __construct() {
        // Register autoload callback
        spl_autoload_register([$this, 'autoload']);

        // Default namespace mappings
        $this->add_namespace_directory('Zaplane', ZAPLANE_ROOT_DIR_PATH . 'includes/');
        $this->add_namespace_directory('Zaplane\\Integrations', ZAPLANE_ROOT_DIR_PATH . 'integrations/'); // <-- your root integrations
    }
}

// Initialize autoloader
Autoload::get_instance();
