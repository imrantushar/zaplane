<?php
namespace Zaplane\Modules;

use Zaplane\Core\ModuleInterface;
use Zaplane\Classes\Container;

if (!defined('ABSPATH')) exit;

class Ajax implements ModuleInterface {
    protected Container $container;
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
        $workspace = new Ajax\Workflows();
        $workspace->register();
    }
}