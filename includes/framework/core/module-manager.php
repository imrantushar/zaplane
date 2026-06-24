<?php
namespace Zaplane\Framework\Core;

use Zaplane\Framework\Classes\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ModuleManager {


	protected static ?self $instance = null;
	protected array $modules = [];
	protected Container $container;

	public static function init( Container $container ): self {
		if ( ! self::$instance ) {
			self::$instance = new self( $container );
		}
		return self::$instance;
	}

	private function __construct( Container $container ) {
		$this->container = $container;
		$this->load_modules();
	}

	protected function load_modules(): void {

		$module_classes = [
			\Zaplane\Admin::class,
			\Zaplane\Ajax::class,
			\Zaplane\API::class,
			\Zaplane\Modules\AbandonedCart\AbandonedCartModule::class,
			\Zaplane\Modules\BirthdayCron\BirthdayCronModule::class,
			\Zaplane\Modules\InactiveCustomer\InactiveCustomerModule::class,
		];

		foreach ( $module_classes as $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}
			$module = $class::init( $this->container );
			$this->modules[] = $module;
		}
	}

	public function get_modules(): array {
		return $this->modules;
	}

	public function boot(): void {
		foreach ( $this->modules as $module ) {
			$module->register_hooks();
		}
	}
}
