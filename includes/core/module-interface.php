<?php
namespace Zaplane\Core;

use Zaplane\Classes\Container;

if (!defined('ABSPATH')) exit;

interface ModuleInterface {

    /**
     * Initialize the module singleton with container
     *
     * @param Container $container
     * @return self
     */
    public static function init(Container $container): self;

    /**
     * Register WordPress hooks for the module
     */
    public function register_hooks(): void;
}
