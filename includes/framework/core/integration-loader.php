<?php
namespace Zaplane\Framework\Core;

if (!defined('ABSPATH')) exit;

class IntegrationLoader {

    protected static array $registry = [];
    protected static array $instances = [];

    public static function init(): self {
        if (!empty(self::$registry)) return new self();
        self::$registry = require ZAPLANE_INCLUDES_DIR_PATH . 'framework/core/integration-registry.php';
        do_action('zaplane_register_integrations_registry', self::$registry);

        return new self();
    }

    public static function get(string $slug): ?object {
        if (!empty(self::$instances[$slug])) return self::$instances[$slug];
        if (empty(self::$registry[$slug])) return null;
        $file = ZAPLANE_INTEGRATION_DIR_PATH . '/' . basename(self::$registry[$slug]['file']);
        $class = self::$registry[$slug]['class'];
        if (!class_exists($class) && file_exists($file)) {
            require_once $file;
        }
        if (!class_exists($class)) return null;

        $instance = new $class();
        self::$instances[$slug] = $instance;

        return $instance;
    }

    // Get all registered integration slugs (without loading instances)
    public static function getAllSlugs(): array {
        self::init();
        return array_keys(self::$registry);
    }

    // Get registry metadata without loading instances
    public static function getRegistry(): array {
        self::init();
        return self::$registry;
    }

    // Only Used for WP Cli JSON Generator - loads ALL integrations
    public static function all(): array {
        self::init();

        $all = [];
        foreach (self::$registry as $slug => $meta) {
            $instance = self::get($slug);
            if ($instance) {
                $all[$slug] = $instance;
            }
        }
        return $all;
    }
}
