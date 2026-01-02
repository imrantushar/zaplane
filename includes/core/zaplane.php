<?php
namespace Zaplane\Core;

if (!defined('ABSPATH')) exit;

use Zaplane\Classes\Container;
use Zaplane\Classes\IntegrationLoader;
use Zaplane\Modules\Automation;

final class Zaplane {

    private static ?self $instance = null;
    public Container $container;

    private function __construct() {
        $this->set_global_settings();
        $this->load_dependencies();

        $this->container = $this->boot_container();

        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
        add_action('zaplane_loaded', [$this, 'init_plugin']);
    }

    public static function init(): self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function set_global_settings(): void {
        $GLOBALS['zaplane_settings'] = json_decode(get_option('zaplane_settings', '{}'));
    }

    private function load_dependencies(): void {
        require_once ZAPLANE_INCLUDES_DIR_PATH . 'autoload.php';
    }

    private function boot_container(): Container {
        $container = new Container();
        // Integration loader
        $container->set('integrations', fn($c) => IntegrationLoader::init($c));
        // Optional core modules
        $container->set('modules', fn($c) => ModuleManager::init($c));
        return $container;
    }

    public function on_plugins_loaded(): void {
        do_action('zaplane_loaded');
    }

    public function init_plugin(): void {
        // Initialize modules first
        $modules = $this->container->get('modules');
        $modules->boot(); // Calls register_hooks() on all modules
        do_action('zaplane_init');
    }
}

// Bootstrap plugin
Zaplane::init();
