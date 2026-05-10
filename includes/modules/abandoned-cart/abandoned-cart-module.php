<?php

namespace Zaplane\Modules\AbandonedCart;

use Zaplane\Framework\Core\ModuleInterface;
use Zaplane\Framework\Classes\Container;
use Zaplane\Modules\AbandonedCart\Woo\WooCartTrackingInit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AbandonedCartModule implements ModuleInterface {

	protected static ?self $instance = null;
	protected Container $container;

	public static function init( Container $container ): self {
		if ( ! self::$instance ) {
			self::$instance = new self( $container );
		}
		return self::$instance;
	}

	private function __construct( Container $container ) {
		$this->container = $container;
	}

	public function register_hooks(): void {
		( new WooCartTrackingInit() )->register();

		add_action( 'zaplane_ab_cart_check_abandoned', [ AbandonedCartRunner::class, 'run_abandoned' ] );
		add_action( 'zaplane_ab_cart_check_lost', [ AbandonedCartRunner::class, 'run_lost' ] );

		add_action( 'init', [ AbandonedCartRunner::class, 'schedule_recurring' ] );
	}
}
