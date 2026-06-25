<?php

namespace Zaplane\Modules\InactiveCustomer;

use Zaplane\Framework\Core\ModuleInterface;
use Zaplane\Framework\Classes\Container;
use Zaplane\Integrations\Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class InactiveCustomerModule implements ModuleInterface {

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
		add_action( 'init', [ Woocommerce::class, 'register_inactive_customer_cron' ] );
		add_action( 'zaplane_woo_inactive_customer_check', [ Woocommerce::class, 'handle_inactive_customer_check' ] );

		// Remove inactive-customer tags immediately when a new order is placed.
		add_action( 'woocommerce_checkout_order_created', [ Woocommerce::class, 'handle_inactive_order_placed' ], 10, 1 );
		add_action( 'woocommerce_order_status_processing', [ Woocommerce::class, 'handle_inactive_order_placed' ], 10, 1 );
	}
}
