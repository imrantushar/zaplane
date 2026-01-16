<?php
namespace Zaplane\Modules;

use Zaplane\Classes\Container;
use Zaplane\Core\ModuleInterface;
use Zaplane\Modules\Admin\Menu;
use Zaplane\Modules\Admin\Assets;

if (!defined('ABSPATH')) exit;

class Admin implements ModuleInterface {
    protected Container $container;
    protected ?Menu $menu = null;
    protected ?Assets $assets = null;
    protected static ?self $instance = null;

    public static function init(Container $container): self {
        if (!self::$instance) {
            self::$instance = new self($container);
            self::$instance->register_hooks();
        }
        return self::$instance;
    }

    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    public function register_hooks(): void {
        if (!is_admin()) return; // frontend safety

        $this->boot();
    }

    public function boot(): void {
        if (!$this->menu) {
            $this->menu = new Menu();
            $this->menu->register();
        }
        if (!$this->assets) {
            $this->assets = new Assets();
            $this->assets->register();
        }
    }
}