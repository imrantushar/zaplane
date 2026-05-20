<?php

namespace Zaplane\Modules\BirthdayCron;

use Zaplane\Framework\Core\ModuleInterface;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Classes\Query;
use Zaplane\Integrations\Gemcrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BirthdayCronModule implements ModuleInterface {

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
		add_action( 'init', [ Gemcrm::class, 'register_birthday_cron' ] );

		add_action( 'zaplane_gemcrm_birthday_check', [ Gemcrm::class, 'handle_birthday_check' ] );
		add_action( 'zaplane_gemcrm_birthday_process_batch', function ( array $args ) {
			Gemcrm::handle_birthday_batch( $args ?? [] );
		} );

		add_action( 'woocommerce_order_status_completed', [ $this, 'handle_birthday_purchase' ], 10, 1 );
	}

	/**
	 * When a WooCommerce order completes, check if any applied coupon is a birthday
	 * coupon. If so, apply the configured purchase tag to the GemCRM contact.
	 */
	public function handle_birthday_purchase( int $order_id ): void {
		if ( ! class_exists( 'WC_Order' ) ) {
			return;
		}
		if ( ! class_exists( \GemCrm\Database\Models\Tag::class ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_coupon_codes() as $code ) {
			$coupon = new \WC_Coupon( $code );
			if ( ! $coupon->get_id() ) {
				continue;
			}

			$contact_id = (int) get_post_meta( $coupon->get_id(), '_zaplane_birthday_contact_id', true );
			if ( ! $contact_id ) {
				continue;
			}

			$purchase_tag_id = $this->get_birthday_purchase_tag_id();
			if ( ! $purchase_tag_id ) {
				continue;
			}

			\GemCrm\Database\Models\Tag::attach_single( $contact_id, $purchase_tag_id );

			// Only process once per order (first birthday coupon wins).
			break;
		}
	}

	/**
	 * Returns the first purchase_tag_id found across all active birthday workflows.
	 */
	private function get_birthday_purchase_tag_id(): int {
		$workflows = Query::get_active_workflows_for_event( 'zaplane_gemcrm_contact_birthday' );

		foreach ( $workflows as $trigger ) {
			$tag_id = (int) ( $trigger['graph_node']['data']['config']['purchase_tag_id'] ?? 0 );
			if ( $tag_id > 0 ) {
				return $tag_id;
			}
		}

		return 0;
	}
}
