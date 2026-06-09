<?php

namespace Zaplane\Modules\AbandonedCart;

use Zaplane\Framework\Core\ModuleInterface;
use Zaplane\Framework\Classes\Container;

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
		// All tracking, scheduling, and API is now handled by the GemCRM Abandoned Cart addon.
		// This module exists only to keep the zaplane plugin's ModuleInterface contract satisfied
		// and to ensure the workflow integration (triggers/actions) is loaded via
		// zaplane/integrations/abandoned-cart.php.
	}
}
