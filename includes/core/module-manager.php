<?php
namespace Zaplane\Core;

use Zaplane\Classes\Container;

if (!defined('ABSPATH')) exit;

class ModuleManager {

    protected static ?self $instance = null;
    protected array $modules = [];
    protected Container $container;

    public static function init(Container $container): self {
        if (!self::$instance) {
            self::$instance = new self($container);
        }
        return self::$instance;
    }

    private function __construct(Container $container) {
        $this->container = $container;
        $this->load_modules();
    }

    protected function load_modules(): void {
        // List all module classes here
        $module_classes = [
            \Zaplane\Modules\Automation::class,
            \Zaplane\Modules\Admin::class,
            \Zaplane\Modules\Ajax::class,
            \Zaplane\Modules\API::class,
        ];

        foreach ($module_classes as $class) {
            if (!class_exists($class)) continue;
            $module = $class::init($this->container); // ✅ pass container
            $module->register_hooks();
            $this->modules[] = $module;
        }
    }

    public function get_modules(): array {
        return $this->modules;
    }

    public function boot(): void {
        foreach ($this->modules as $module) {
            $module->register_hooks();
        }
    }
}
