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
    }

    /**
     * Constructor: register autoloader
     */
    private function __construct() {
        // Register autoload callback
        spl_autoload_register([$this, 'autoload']);

        // Default namespace mappings
        $this->add_namespace_directory('Zaplane', ZAPLANE_ROOT_DIR_PATH . 'includes/');
        $this->add_namespace_directory('Zaplane\\Integrations', ZAPLANE_ROOT_DIR_PATH . 'integrations/');
        $this->add_namespace_directory('Zaplane\\Integration', ZAPLANE_ROOT_DIR_PATH . 'integrations/');
        $this->add_namespace_directory('Zaplane\\Integration', ZAPLANE_ROOT_DIR_PATH . 'integration/');
    }
}

// Initialize autoloader
Autoload::get_instance();
