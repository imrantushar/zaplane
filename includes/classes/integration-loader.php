<?php
namespace Zaplane\Classes;

if (!defined('ABSPATH')) exit;

class IntegrationLoader {

    protected static array $registry = [];
    protected static array $instances = [];

    public static function init(): self {
        if (!empty(self::$registry)) return new self();
        self::$registry = require ZAPLANE_INCLUDES_DIR_PATH . 'core/integration-registry.php';
        do_action('zaplane_register_integrations_registry', self::$registry);

        return new self();
    }

    public static function get(string $slug): ?object {
        if (!empty(self::$instances[$slug])) return self::$instances[$slug];
        if (empty(self::$registry[$slug])) return null;

        error_log(print_r($slug, true));
        error_log(print_r($slug, true));
        $file = ZAPLANE_INTEGRATION_DIR_PATH . '/' . basename(self::$registry[$slug]['file']);
        error_log(print_r($file, true));
        $class = self::$registry[$slug]['class'];

        if (!class_exists($class) && file_exists($file)) {
            require_once $file;
        }
        if (!class_exists($class)) return null;

        $instance = new $class();
        self::$instances[$slug] = $instance;

        return $instance;
    }
}
