<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Storeengine extends IntegrationBase {

	public static function get_slug(): string {
		return 'storeengine';
	}

	public static function get_name(): string {
		return 'StoreEngine';
	}

	public static function get_icon(): string {
		return 'storeengine.svg';
	}

	/**
	 * Whether a StoreEngine addon is active.
	 *
	 * StoreEngine exposes addon status via \StoreEngine\Utils\Helper::get_addon_active_status().
	 * Guarded so the integration never fatals when StoreEngine (or the helper) is missing.
	 */
	private static function addon_active( string $addon ): bool {
		if (
			class_exists( '\StoreEngine\Utils\Helper' ) &&
			method_exists( '\StoreEngine\Utils\Helper', 'get_addon_active_status' )
		) {
			return (bool) \StoreEngine\Utils\Helper::get_addon_active_status( $addon );
		}

		return false;
	}

	/**
	 * Always expose addon-specific triggers/actions, but flag them as disabled
	 * (with a reason) when the underlying StoreEngine addon is not active. The
	 * admin UI keeps the label visible and greys the item out, surfacing the
	 * "Requires the StoreEngine X addon" hint instead of hiding it entirely.
	 *
	 * @param array  $items Map of slug => definition.
	 * @param string $addon StoreEngine addon key (e.g. 'subscription').
	 * @param string $label Human readable addon name for the hint.
	 */
	private static function gate( array $items, string $addon, string $label ): array {
		$active = self::addon_active( $addon );

		foreach ( $items as &$item ) {
			// Static dependency marker — deterministic in the built manifest,
			// independent of the build machine's addon status. The picker can
			// use this to re-evaluate availability live against the site's
			// active addons if desired.
			$item['requires_addon'] = $addon;

			// Build-time snapshot of availability for the picker, captured when
			// `wp build:integration` runs. Re-run the build after activating the
			// addon to flip the item from disabled to enabled.
			if ( ! $active ) {
				$item['disabled']        = true;
				$item['disabled_reason'] = sprintf(
					/* translators: %s: StoreEngine addon name. */
					__( 'Requires the StoreEngine %s addon to be active.', 'zaplane' ),
					$label
				);
			}
		}
		unset( $item );

		return $items;
	}

	/*
	|--------------------------------------------------------------------------
	| TRIGGERS
	|--------------------------------------------------------------------------
	*/

	public static function get_triggers(): array {
		$triggers = [
			// --- Orders ---
			'product_purchased' => [
				'label' => 'Product Purchased',
				'hook'  => 'storeengine/checkout/after_place_order',
			],
			'order_paid' => [
				'label' => 'Order Paid (Payment Complete)',
				'hook'  => 'storeengine/payment_complete',
			],
			'order_status_update' => [
				'label' => 'Order Status Updated (any)',
				'hook'  => 'storeengine/order/status_changed',
			],
			'order_status_on_hold' => [
				'label' => 'Order Status Set To On Hold',
				'hook'  => 'storeengine/order_status_on_hold',
			],
			'order_status_pending_payment' => [
				'label' => 'Order Status Set To Pending Payment',
				'hook'  => 'storeengine/order_status_pending_payment',
			],
			'order_status_processing' => [
				'label' => 'Order Status Set To Processing',
				'hook'  => 'storeengine/order_status_processing',
			],
			'order_status_completed' => [
				'label' => 'Order Status Set To Completed',
				'hook'  => 'storeengine/order_status_completed',
			],
			'order_status_cancelled' => [
				'label' => 'Order Status Set To Cancelled',
				'hook'  => 'storeengine/order_status_cancelled',
			],
			'order_status_failed' => [
				'label' => 'Order Status Set To Payment Failed',
				'hook'  => 'storeengine/order_status_payment_failed',
			],
			'order_status_refunded' => [
				'label' => 'Order Status Set To Refunded',
				'hook'  => 'storeengine/order_status_refunded',
			],
			'order_status_draft' => [
				'label' => 'Order Status Set To Draft',
				'hook'  => 'storeengine/order_status_auto-draft',
			],
			'order_status_trash' => [
				'label' => 'Order Status Set To Trash',
				'hook'  => 'storeengine/order_status_trash',
			],
			'order_restored' => [
				'label' => 'Order Restored',
				'hook'  => 'storeengine/order/status_changed',
			],
			'order_cancelled_by_customer' => [
				'label' => 'Order Cancelled By Customer',
				'hook'  => 'storeengine/order/order_cancelled',
			],
			'order_fully_refunded' => [
				'label' => 'Order Fully Refunded',
				'hook'  => 'storeengine/order/fully_refunded',
			],
			'order_partially_refunded' => [
				'label' => 'Order Partially Refunded',
				'hook'  => 'storeengine/order/partially_refunded',
			],
			'payment_refunded' => [
				'label' => 'Subscription Payment Refunded',
				'hook'  => 'storeengine/subscription/payment_refunded',
			],
			'order_coupon_applied' => [
				'label' => 'Coupon Applied To Order',
				'hook'  => 'storeengine/order/applied_coupon',
			],
			'order_item_shipped' => [
				'label' => 'Order Item Shipped',
				'hook'  => 'storeengine/order/item_shipped',
			],
			'order_fully_delivered' => [
				'label' => 'Order Fully Delivered',
				'hook'  => 'storeengine/all_product_delivered',
			],
			'order_customer_note_added' => [
				'label' => 'Customer Note Added to Order',
				'hook'  => 'storeengine/order/new_customer_note',
			],
			'order_customer_note_deleted' => [
				'label' => 'Customer Note Deleted From Order',
				'hook'  => 'storeengine/order/note_deleted',
			],

			// --- Customers ---
			'customer_created' => [
				'label' => 'Customer Created',
				'hook'  => 'storeengine/checkout/customer_created',
			],

			// --- Products / Inventory ---
			'product_created' => [
				'label' => 'Product Created',
				'hook'  => 'storeengine/product/after/object_save',
			],
			'product_updated' => [
				'label' => 'Product Updated',
				'hook'  => 'storeengine/product/updated',
			],
			'product_stock_changed' => [
				'label' => 'Product Stock Status Changed (any)',
				'hook'  => 'storeengine/stock_status_changed',
			],
			'product_out_of_stock' => [
				'label' => 'Product Went Out Of Stock',
				'hook'  => 'storeengine/stock_status_changed',
			],
			'product_back_in_stock' => [
				'label' => 'Product Back In Stock',
				'hook'  => 'storeengine/stock_status_changed',
			],

			// --- Cart / Checkout ---
			'checkout_order_processed' => [
				'label' => 'Checkout Order Processed',
				'hook'  => 'storeengine/checkout/order_processed',
			],
			'add_to_cart' => [
				'label' => 'Product Added To Cart',
				'hook'  => 'storeengine/cart/add_to_cart',
			],
		];

		// Addon triggers are always listed; gate() flags them disabled (with a
		// reason) when the relevant StoreEngine addon is inactive.
		$triggers += self::gate( [
			'subscription_created' => [
				'label' => 'Subscription Created',
				'hook'  => 'storeengine/after_create_subscription',
			],
			'subscription_status_changed' => [
				'label' => 'Subscription Status Changed (any)',
				'hook'  => 'storeengine/subscription/status_changed',
			],
			'subscription_activated' => [
				'label' => 'Subscription Activated',
				'hook'  => 'storeengine/subscription/status_active',
			],
			'subscription_on_hold' => [
				'label' => 'Subscription Paused (On Hold)',
				'hook'  => 'storeengine/subscription/status_on_hold',
			],
			'subscription_cancelled' => [
				'label' => 'Subscription Cancelled',
				'hook'  => 'storeengine/subscription/status_cancelled',
			],
			'subscription_expired' => [
				'label' => 'Subscription Expired',
				'hook'  => 'storeengine/subscription/status_expired',
			],
			'subscription_renewed' => [
				'label' => 'Subscription Renewal Payment Complete',
				'hook'  => 'storeengine/subscription/renewal_payment_complete',
			],
			'subscription_payment_complete' => [
				'label' => 'Subscription Payment Complete',
				'hook'  => 'storeengine/subscription/payment_complete',
			],
			'subscription_renewal_failed' => [
				'label' => 'Subscription Renewal Failed',
				'hook'  => 'storeengine/subscription/failed_to_create_renewal_order',
			],
			'subscription_trial_ended' => [
				'label' => 'Subscription Trial Ended',
				'hook'  => 'storeengine/subscription/trial_ended',
			],
			'subscription_renewal_order_created' => [
				'label' => 'Subscription Renewal Order Created',
				'hook'  => 'storeengine/api/after_create_renewal_order',
			],
		], 'subscription', 'Subscription' );

		$triggers += self::gate( [
			'vendor_registered' => [
				'label' => 'Vendor Registered',
				'hook'  => 'storeengine/multi_vendor/vendor_registered',
			],
			'vendor_approved' => [
				'label' => 'Vendor Approved',
				'hook'  => 'storeengine/multi_vendor/vendor_approved',
			],
			'vendor_suspended' => [
				'label' => 'Vendor Suspended',
				'hook'  => 'storeengine/multi_vendor/vendor_suspended',
			],
			'vendor_commission_computed' => [
				'label' => 'Vendor Commission Computed',
				'hook'  => 'storeengine/multi_vendor/commission_computed',
			],
			'vendor_withdrawal_requested' => [
				'label' => 'Vendor Withdrawal Requested',
				'hook'  => 'storeengine/multi_vendor/withdrawal_requested',
			],
			'vendor_withdrawal_status_changed' => [
				'label' => 'Vendor Withdrawal Status Changed',
				'hook'  => 'storeengine/multi_vendor/withdrawal_status_changed',
			],
		], 'multi_vendor', 'Multi-Vendor' );

		$triggers += self::gate( [
			'affiliate_registered' => [
				'label' => 'Affiliate Registered',
				'hook'  => 'storeengine/addons/affiliate/after_registration',
			],
			'affiliate_status_updated' => [
				'label' => 'Affiliate Status Updated',
				'hook'  => 'storeengine/addons/affiliate/update_status',
			],
			'affiliate_commission_status_updated' => [
				'label' => 'Affiliate Commission Status Updated',
				'hook'  => 'storeengine/addons/affiliate/update_commission_status',
			],
			'affiliate_payout_status_updated' => [
				'label' => 'Affiliate Payout Status Updated',
				'hook'  => 'storeengine/addons/affiliate/update_payout_status',
			],
		], 'affiliate', 'Affiliate' );

		return $triggers;
	}

	/*
	|--------------------------------------------------------------------------
	| TRIGGER PAYLOAD RESOLVERS
	|--------------------------------------------------------------------------
	*/

	private static function resolve_order_payload( $order, array $extra = [] ) {
		if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
			$order_id = (int) $order->get_id();
		} elseif ( is_numeric( $order ) ) {
			$order_id = (int) $order;
		} else {
			return false;
		}

		if ( ! $order_id || ! function_exists( 'storeengine_get_order' ) ) {
			return false;
		}

		$order_obj = storeengine_get_order( $order_id );

		if ( ! $order_obj || is_wp_error( $order_obj ) ) {
			return false;
		}

		$items = array_map( function ( $item ) {
			if ( is_array( $item ) ) {
				return [
					'product_id' => $item['product_id'] ?? '',
					'name'       => $item['name'] ?? '',
					'quantity'   => $item['quantity'] ?? '',
					'total'      => $item['total'] ?? '',
				];
			}

			if ( is_object( $item ) ) {
				return [
					'product_id' => method_exists( $item, 'get_product_id' ) ? $item->get_product_id() : '',
					'name'       => method_exists( $item, 'get_name' ) ? $item->get_name() : '',
					'quantity'   => method_exists( $item, 'get_quantity' ) ? $item->get_quantity() : '',
					'total'      => method_exists( $item, 'get_total' ) ? $item->get_total() : '',
				];
			}

			return [];
		}, array_values( $order_obj->get_items() ) );

		return array_merge( [
			'order_id'       => $order_id,
			'order_number'   => $order_obj->get_order_number(),
			'order_status'   => $order_obj->get_status(),
			'total'          => $order_obj->get_total(),
			'currency'       => $order_obj->get_currency(),
			'payment_method' => $order_obj->get_payment_method(),
			'customer_id'    => method_exists( $order_obj, 'get_customer_id' ) ? $order_obj->get_customer_id() : '',
			'customer_email' => method_exists( $order_obj, 'get_billing_email' )
				? $order_obj->get_billing_email() : '',
			'customer_name'  => method_exists( $order_obj, 'get_billing_first_name' )
				? trim(
					$order_obj->get_billing_first_name() . ' ' .
					$order_obj->get_billing_last_name()
				)
				: '',
			'items'          => $items,
		], $extra );
	}

	private static function resolve_product_payload( $product, array $extra = [] ) {
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			$product_id = (int) $product->get_id();
			$product_obj = $product;
		} elseif ( is_numeric( $product ) ) {
			$product_id = (int) $product;
			$product_obj = function_exists( 'storeengine_get_product' ) ? storeengine_get_product( $product_id ) : null;
		} else {
			return false;
		}

		if ( ! $product_id ) {
			return false;
		}

		$data = [ 'product_id' => $product_id ];

		if ( $product_obj && ! is_wp_error( $product_obj ) ) {
			$data['name']           = method_exists( $product_obj, 'get_name' ) ? $product_obj->get_name() : get_the_title( $product_id );
			$data['stock_quantity'] = method_exists( $product_obj, 'get_stock_quantity' ) ? $product_obj->get_stock_quantity() : '';
			$data['stock_status']   = method_exists( $product_obj, 'get_stock_status' ) ? $product_obj->get_stock_status() : '';
		} else {
			$data['name'] = get_the_title( $product_id );
		}

		return array_merge( $data, $extra );
	}

	private static function resolve_subscription_payload( $subscription, array $extra = [] ) {
		if ( is_object( $subscription ) && method_exists( $subscription, 'get_id' ) ) {
			$sub = $subscription;
		} elseif ( is_numeric( $subscription ) && class_exists( '\StoreEngine\Addons\Subscription\Classes\Subscription' ) ) {
			try {
				$sub = new \StoreEngine\Addons\Subscription\Classes\Subscription( (int) $subscription );
			} catch ( \Throwable $e ) {
				return false;
			}
		} else {
			return false;
		}

		$data = [
			'subscription_id' => (int) $sub->get_id(),
			'status'          => method_exists( $sub, 'get_status' ) ? $sub->get_status() : '',
			'total'           => method_exists( $sub, 'get_total' ) ? $sub->get_total() : '',
			'currency'        => method_exists( $sub, 'get_currency' ) ? $sub->get_currency() : '',
			'customer_id'     => method_exists( $sub, 'get_customer_id' ) ? $sub->get_customer_id() : '',
			'customer_email'  => method_exists( $sub, 'get_billing_email' ) ? $sub->get_billing_email() : '',
		];

		if ( method_exists( $sub, 'get_next_payment_date' ) ) {
			$next = $sub->get_next_payment_date();
			$data['next_payment_date'] = is_object( $next ) && method_exists( $next, 'format' ) ? $next->format( 'Y-m-d H:i:s' ) : '';
		}

		return array_merge( $data, $extra );
	}

	/*
	|--------------------------------------------------------------------------
	| TRIGGER DISPATCH
	|--------------------------------------------------------------------------
	*/

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {

			/* ---------------- Orders ---------------- */

			case 'product_purchased':
			case 'checkout_order_processed':
				$order = $args[0] ?? 0;
				return self::resolve_order_payload( $order );

			case 'order_paid':
				$order_id       = $args[0] ?? 0;
				$transaction_id = $args[1] ?? '';
				return self::resolve_order_payload( $order_id, [ 'transaction_id' => $transaction_id ] );

			case 'order_status_update':
				$order_id = $args[0] ?? 0;
				$old      = $args[1] ?? '';
				$new      = $args[2] ?? '';
				return self::resolve_order_payload( $order_id, [
					'old_status' => $old,
					'new_status' => $new,
				] );

			case 'order_status_on_hold':
			case 'order_status_pending_payment':
			case 'order_status_processing':
			case 'order_status_completed':
			case 'order_status_cancelled':
			case 'order_status_failed':
			case 'order_status_refunded':
			case 'order_status_draft':
			case 'order_status_trash':
				$order_id   = $args[0] ?? 0;
				$transition = $args[2] ?? [];
				$extra      = [];
				if ( is_array( $transition ) ) {
					$extra['old_status'] = $transition['from'] ?? '';
					$extra['new_status'] = $transition['to'] ?? '';
				}
				return self::resolve_order_payload( $order_id, $extra );

			case 'order_restored':
				// storeengine/order/status_changed: ( order_id, old_status, new_status, order )
				$order_id = $args[0] ?? 0;
				$old      = $args[1] ?? '';
				$new      = $args[2] ?? '';
				if ( 'trash' !== $old ) {
					return false;
				}
				return self::resolve_order_payload( $order_id, [
					'old_status'      => $old,
					'restored_status' => $new,
				] );

			case 'order_cancelled_by_customer':
				$order_id = $args[0] ?? 0;
				return self::resolve_order_payload( $order_id );

			case 'order_fully_refunded':
				$order_id  = $args[0] ?? 0;
				$refund_id = $args[1] ?? 0;
				return self::resolve_order_payload( $order_id, [ 'refund_id' => $refund_id ] );

			case 'order_partially_refunded':
				$order_id        = $args[0] ?? 0;
				$refund_id       = $args[1] ?? 0;
				$remaining_amount = $args[2] ?? 0;
				return self::resolve_order_payload( $order_id, [
					'refund_id'        => $refund_id,
					'remaining_amount' => $remaining_amount,
				] );

			case 'payment_refunded':
				$order_id        = $args[0] ?? 0;
				$refunded_amount = $args[1] ?? 0;
				$refunded_reason = $args[2] ?? '';
				return self::resolve_order_payload( $order_id, [
					'refunded_amount' => $refunded_amount,
					'refunded_reason' => $refunded_reason,
				] );

			case 'order_coupon_applied':
				$coupon = $args[0] ?? null;
				$order  = $args[1] ?? null;
				$code   = ( is_object( $coupon ) && method_exists( $coupon, 'get_code' ) ) ? $coupon->get_code() : '';
				return self::resolve_order_payload( $order, [ 'coupon_code' => $code ] );

			case 'order_item_shipped':
				$order_id      = $args[0] ?? 0;
				$order_item_id = $args[1] ?? 0;
				$product_id    = $args[2] ?? 0;
				$new_status    = $args[4] ?? '';
				return self::resolve_order_payload( $order_id, [
					'order_item_id'  => $order_item_id,
					'product_id'     => $product_id,
					'shipment_status' => $new_status,
				] );

			case 'order_fully_delivered':
				$order_id = $args[0] ?? 0;
				return self::resolve_order_payload( $order_id );

			case 'order_customer_note_added':
				$note  = $args[0] ?? '';
				$order = $args[1] ?? null;
				if ( ! is_object( $order ) ) {
					return false;
				}
				return self::resolve_order_payload( $order, [ 'note' => $note ] );

			case 'order_customer_note_deleted':
				$note_id  = $args[0] ?? 0;
				$note_obj = $args[1] ?? null;
				if ( ! is_object( $note_obj ) ) {
					return false;
				}
				$order_id = $note_obj->order_id ?? 0;
				if ( ! $order_id ) {
					return false;
				}
				return self::resolve_order_payload( $order_id, [
					'deleted_note_id' => $note_id,
					'deleted_note'    => $note_obj->content ?? '',
				] );

			/* ---------------- Customers ---------------- */

			case 'customer_created':
				$user_id  = $args[0] ?? 0;
				$userdata = $args[1] ?? [];
				if ( ! $user_id ) {
					return false;
				}
				$user = get_userdata( $user_id );
				return [
					'customer_id' => (int) $user_id,
					'email'       => $user ? $user->user_email : ( $userdata['user_email'] ?? '' ),
					'first_name'  => $user ? $user->first_name : '',
					'last_name'   => $user ? $user->last_name : '',
					'username'    => $user ? $user->user_login : '',
				];

			/* ---------------- Products / Inventory ---------------- */

			case 'product_created':
				// storeengine/product/after/object_save: ( product, $create )
				$product = $args[0] ?? null;
				$created = $args[1] ?? false;
				if ( ! $created ) {
					return false; // only fire on creation
				}
				return self::resolve_product_payload( $product );

			case 'product_updated':
				$product_id = $args[0] ?? 0;
				return self::resolve_product_payload( $product_id );

			case 'product_stock_changed':
			case 'product_out_of_stock':
			case 'product_back_in_stock':
				$product_id   = $args[0] ?? 0;
				$variation_id = $args[1] ?? 0;
				$old_status   = $args[2] ?? '';
				$new_status   = $args[3] ?? '';

				if ( 'product_out_of_stock' === $node['event'] && 'outofstock' !== $new_status ) {
					return false;
				}
				if ( 'product_back_in_stock' === $node['event'] && 'instock' !== $new_status ) {
					return false;
				}

				return self::resolve_product_payload( $product_id, [
					'variation_id' => $variation_id,
					'old_status'   => $old_status,
					'new_status'   => $new_status,
				] );

			/* ---------------- Cart ---------------- */

			case 'add_to_cart':
				return [
					'cart_id'      => $args[0] ?? '',
					'price_id'     => $args[1] ?? '',
					'product_id'   => $args[2] ?? '',
					'variation_id' => $args[3] ?? '',
					'quantity'     => $args[4] ?? '',
				];

			/* ---------------- Subscriptions ---------------- */

			case 'subscription_created':
			case 'subscription_activated':
			case 'subscription_on_hold':
			case 'subscription_cancelled':
			case 'subscription_expired':
			case 'subscription_payment_complete':
				$sub = $args[0] ?? null;
				return self::resolve_subscription_payload( $sub );

			case 'subscription_status_changed':
				$subscription_id = $args[0] ?? 0;
				$old             = $args[1] ?? '';
				$new             = $args[2] ?? '';
				$sub             = $args[3] ?? $subscription_id;
				return self::resolve_subscription_payload( $sub, [
					'old_status' => $old,
					'new_status' => $new,
				] );

			case 'subscription_renewed':
				$sub        = $args[0] ?? null;
				$last_order = $args[1] ?? null;
				$order_id   = ( is_object( $last_order ) && method_exists( $last_order, 'get_id' ) ) ? $last_order->get_id() : '';
				return self::resolve_subscription_payload( $sub, [ 'renewal_order_id' => $order_id ] );

			case 'subscription_renewal_failed':
				$sub = $args[1] ?? null; // ( $type|$order, $subscription )
				return self::resolve_subscription_payload( $sub );

			case 'subscription_trial_ended':
				$subscription_id = $args[0] ?? 0;
				return self::resolve_subscription_payload( $subscription_id );

			case 'subscription_renewal_order_created':
				$order = $args[0] ?? null;
				return self::resolve_order_payload( $order, [ 'is_renewal' => true ] );

			/* ---------------- Multi-Vendor ---------------- */

			case 'vendor_registered':
			case 'vendor_approved':
			case 'vendor_suspended':
				$vendor = $args[0] ?? null;
				$vendor_id = '';
				$store_name = '';
				if ( is_object( $vendor ) ) {
					$vendor_id  = method_exists( $vendor, 'get_id' ) ? $vendor->get_id() : ( $vendor->id ?? '' );
					$store_name = method_exists( $vendor, 'get_store_name' ) ? $vendor->get_store_name() : ( $vendor->store_name ?? '' );
				} elseif ( is_numeric( $vendor ) ) {
					$vendor_id = (int) $vendor;
				}
				return [
					'vendor_id'  => $vendor_id,
					'store_name' => $store_name,
				];

			case 'vendor_commission_computed':
				$order_id = $args[0] ?? 0;
				return self::resolve_order_payload( $order_id, [ 'commission_computed' => true ] );

			case 'vendor_withdrawal_requested':
				return [
					'withdrawal_id' => $args[0] ?? '',
					'user_id'       => $args[1] ?? '',
					'amount'        => $args[2] ?? '',
				];

			case 'vendor_withdrawal_status_changed':
				return [
					'withdrawal_id' => $args[0] ?? '',
					'new_status'    => $args[1] ?? '',
					'old_status'    => $args[2] ?? '',
				];

			/* ---------------- Affiliate ---------------- */

			case 'affiliate_registered':
				return [ 'affiliate_id' => $args[0] ?? '' ];

			case 'affiliate_status_updated':
				return [
					'affiliate_id' => $args[0] ?? '',
					'status'       => $args[1] ?? '',
				];

			case 'affiliate_commission_status_updated':
				return [
					'commission_id' => $args[0] ?? '',
					'status'        => $args[1] ?? '',
				];

			case 'affiliate_payout_status_updated':
				return [
					'payout_id' => $args[0] ?? '',
					'status'    => $args[1] ?? '',
				];
		}//end switch

		return false;
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		$order_sample = [
			'order_id'       => 101,
			'order_number'   => '101',
			'order_status'   => 'completed',
			'total'          => '49.00',
			'currency'       => 'USD',
			'payment_method' => 'stripe',
			'customer_id'    => 5,
			'customer_email' => 'john@example.com',
			'customer_name'  => 'John Doe',
			'items'          => [
				[ 'product_id' => 12, 'name' => 'Pro Plan', 'quantity' => 1, 'total' => '49.00' ],
			],
		];

		$samples = [
			'order_paid'                  => array_merge( $order_sample, [ 'transaction_id' => 'txn_123' ] ),
			'order_status_update'         => array_merge( $order_sample, [ 'old_status' => 'processing', 'new_status' => 'completed' ] ),
			'order_fully_refunded'        => array_merge( $order_sample, [ 'refund_id' => 55 ] ),
			'customer_created'            => [ 'customer_id' => 5, 'email' => 'john@example.com', 'first_name' => 'John', 'last_name' => 'Doe', 'username' => 'john' ],
			'product_updated'             => [ 'product_id' => 12, 'name' => 'Pro Plan', 'stock_quantity' => 20, 'stock_status' => 'instock' ],
			'product_out_of_stock'        => [ 'product_id' => 12, 'name' => 'Pro Plan', 'old_status' => 'instock', 'new_status' => 'outofstock' ],
			'subscription_status_changed' => [ 'subscription_id' => 9, 'status' => 'active', 'old_status' => 'pending', 'new_status' => 'active', 'customer_id' => 5, 'total' => '49.00' ],
			'vendor_registered'           => [ 'vendor_id' => 3, 'store_name' => 'Acme Store' ],
			'affiliate_registered'        => [ 'affiliate_id' => 7 ],
		];

		return $samples[ $trigger ] ?? [];
	}

	/*
	|--------------------------------------------------------------------------
	| ACTIONS
	|--------------------------------------------------------------------------
	*/

	public static function get_actions(): array {
		$actions = [
			// Orders
			'update_order_status' => [ 'label' => 'Update Order Status' ],
			'add_order_note'      => [ 'label' => 'Add Order Note' ],
			'refund_order'        => [ 'label' => 'Refund Order' ],
			// Customers
			'create_customer'     => [ 'label' => 'Create Customer' ],
			'update_customer'     => [ 'label' => 'Update Customer' ],
			// Products / Inventory
			'adjust_stock'        => [ 'label' => 'Adjust Product Stock' ],
			'set_stock_status'    => [ 'label' => 'Set Product Stock Status' ],
			// Coupons
			'create_coupon'       => [ 'label' => 'Create Coupon' ],
		];

		// Addon actions are always listed; gate() flags them disabled (with a
		// reason) when the relevant StoreEngine addon is inactive.
		$actions += self::gate( [
			'update_subscription_status' => [ 'label' => 'Update Subscription Status' ],
		], 'subscription', 'Subscription' );

		$actions += self::gate( [
			'grant_membership'  => [ 'label' => 'Grant Membership' ],
			'revoke_membership' => [ 'label' => 'Revoke Membership' ],
		], 'membership', 'Membership' );

		return $actions;
	}

	public static function get_action_config_schema( string $action ): array {
		switch ( $action ) {

			case 'update_order_status':
				return [
					[ 'key' => 'order_id', 'label' => 'Order ID', 'type' => 'expression', 'required' => true ],
					[
						'key'      => 'order_status',
						'label'    => 'New Status',
						'type'     => 'select',
						'required' => true,
						'options'  => [
							[ 'value' => 'pending_payment', 'label' => 'Pending Payment' ],
							[ 'value' => 'processing', 'label' => 'Processing' ],
							[ 'value' => 'on_hold', 'label' => 'On Hold' ],
							[ 'value' => 'completed', 'label' => 'Completed' ],
							[ 'value' => 'cancelled', 'label' => 'Cancelled' ],
							[ 'value' => 'refunded', 'label' => 'Refunded' ],
							[ 'value' => 'payment_failed', 'label' => 'Payment Failed' ],
						],
					],
					[ 'key' => 'note', 'label' => 'Status Note', 'type' => 'expression', 'required' => false ],
				];

			case 'add_order_note':
				return [
					[ 'key' => 'order_id', 'label' => 'Order ID', 'type' => 'expression', 'required' => true ],
					[ 'key' => 'note', 'label' => 'Note', 'type' => 'textarea', 'required' => true ],
					[
						'key'      => 'note_type',
						'label'    => 'Note Type',
						'type'     => 'select',
						'required' => true,
						'default'  => 'private',
						'options'  => [
							[ 'value' => 'private', 'label' => 'Private (admin only)' ],
							[ 'value' => 'customer', 'label' => 'Customer Note (visible & emailed)' ],
						],
					],
				];

			case 'refund_order':
				return [
					[ 'key' => 'order_id', 'label' => 'Order ID', 'type' => 'expression', 'required' => true ],
					[ 'key' => 'amount', 'label' => 'Refund Amount (leave empty for full refund)', 'type' => 'expression', 'required' => false ],
					[ 'key' => 'reason', 'label' => 'Reason', 'type' => 'expression', 'required' => false ],
					[
						'key'      => 'restock_items',
						'label'    => 'Restock Items',
						'type'     => 'select',
						'required' => false,
						'default'  => 'no',
						'options'  => [
							[ 'value' => 'no', 'label' => 'No' ],
							[ 'value' => 'yes', 'label' => 'Yes' ],
						],
					],
				];

			case 'create_customer':
				return [
					[ 'key' => 'email', 'label' => 'Email', 'type' => 'expression', 'subtype' => 'email', 'required' => true ],
					[ 'key' => 'first_name', 'label' => 'First Name', 'type' => 'expression', 'required' => false ],
					[ 'key' => 'last_name', 'label' => 'Last Name', 'type' => 'expression', 'required' => false ],
					[ 'key' => 'phone', 'label' => 'Billing Phone', 'type' => 'expression', 'required' => false ],
					[ 'key' => 'billing_city', 'label' => 'Billing City', 'type' => 'expression', 'required' => false ],
					[ 'key' => 'billing_country', 'label' => 'Billing Country (2-letter code)', 'type' => 'expression', 'required' => false ],
				];

			case 'update_customer':
				return [
					[ 'key' => 'customer_id', 'label' => 'Customer (User) ID', 'type' => 'expression', 'required' => true ],
					[ 'key' => 'first_name', 'label' => 'First Name', 'type' => 'expression', 'required' => false ],
					[ 'key' => 'last_name', 'label' => 'Last Name', 'type' => 'expression', 'required' => false ],
					[ 'key' => 'phone', 'label' => 'Billing Phone', 'type' => 'expression', 'required' => false ],
					[ 'key' => 'billing_city', 'label' => 'Billing City', 'type' => 'expression', 'required' => false ],
					[ 'key' => 'billing_country', 'label' => 'Billing Country (2-letter code)', 'type' => 'expression', 'required' => false ],
				];

			case 'adjust_stock':
				return [
					[
						'key'      => 'product_id',
						'label'    => 'Product',
						'type'     => 'select',
						'required' => true,
						'dynamic'  => [
							'integration' => 'storeengine',
							'query'       => 'storeengine_product_query',
							'select'      => [ 'value', 'label' ],
						],
					],
					[
						'key'      => 'mode',
						'label'    => 'Operation',
						'type'     => 'select',
						'required' => true,
						'default'  => 'set',
						'options'  => [
							[ 'value' => 'set', 'label' => 'Set To' ],
							[ 'value' => 'increase', 'label' => 'Increase By' ],
							[ 'value' => 'decrease', 'label' => 'Decrease By' ],
						],
					],
					[ 'key' => 'quantity', 'label' => 'Quantity', 'type' => 'expression', 'required' => true ],
				];

			case 'set_stock_status':
				return [
					[
						'key'      => 'product_id',
						'label'    => 'Product',
						'type'     => 'select',
						'required' => true,
						'dynamic'  => [
							'integration' => 'storeengine',
							'query'       => 'storeengine_product_query',
							'select'      => [ 'value', 'label' ],
						],
					],
					[
						'key'      => 'stock_status',
						'label'    => 'Stock Status',
						'type'     => 'select',
						'required' => true,
						'options'  => [
							[ 'value' => 'instock', 'label' => 'In Stock' ],
							[ 'value' => 'outofstock', 'label' => 'Out Of Stock' ],
							[ 'value' => 'onbackorder', 'label' => 'On Backorder' ],
						],
					],
				];

			case 'create_coupon':
				return [
					[ 'key' => 'code', 'label' => 'Coupon Code', 'type' => 'expression', 'required' => true ],
					[
						'key'      => 'coupon_type',
						'label'    => 'Discount Type',
						'type'     => 'select',
						'required' => true,
						'default'  => 'percentage',
						'options'  => [
							[ 'value' => 'percentage', 'label' => 'Percentage' ],
							[ 'value' => 'fixed_cart', 'label' => 'Fixed Cart Discount' ],
							[ 'value' => 'fixed_product', 'label' => 'Fixed Product Discount' ],
						],
					],
					[ 'key' => 'amount', 'label' => 'Amount', 'type' => 'expression', 'required' => true ],
					[ 'key' => 'usage_limit', 'label' => 'Usage Limit (total)', 'type' => 'expression', 'required' => false ],
				];

			case 'update_subscription_status':
				return [
					[ 'key' => 'subscription_id', 'label' => 'Subscription ID', 'type' => 'expression', 'required' => true ],
					[
						'key'      => 'subscription_status',
						'label'    => 'New Status',
						'type'     => 'select',
						'required' => true,
						'options'  => [
							[ 'value' => 'active', 'label' => 'Active' ],
							[ 'value' => 'on_hold', 'label' => 'On Hold (Pause)' ],
							[ 'value' => 'pending', 'label' => 'Pending' ],
							[ 'value' => 'cancelled', 'label' => 'Cancelled' ],
							[ 'value' => 'expired', 'label' => 'Expired' ],
						],
					],
				];

			case 'grant_membership':
				return [
					[ 'key' => 'user_id', 'label' => 'User ID', 'type' => 'expression', 'required' => true ],
					[
						'key'      => 'group_id',
						'label'    => 'Membership Group',
						'type'     => 'select',
						'required' => true,
						'dynamic'  => [
							'integration' => 'storeengine',
							'query'       => 'storeengine_membership_group_query',
							'select'      => [ 'value', 'label' ],
						],
					],
					[ 'key' => 'expiration_date', 'label' => 'Expiration Date (Y-m-d, optional)', 'type' => 'expression', 'required' => false ],
				];

			case 'revoke_membership':
				return [
					[ 'key' => 'user_id', 'label' => 'User ID', 'type' => 'expression', 'required' => true ],
					[
						'key'      => 'group_id',
						'label'    => 'Membership Group (leave empty to revoke all)',
						'type'     => 'select',
						'required' => false,
						'dynamic'  => [
							'integration' => 'storeengine',
							'query'       => 'storeengine_membership_group_query',
							'select'      => [ 'value', 'label' ],
						],
					],
				];
		}//end switch

		return [];
	}

	/*
	|--------------------------------------------------------------------------
	| DYNAMIC QUERIES
	|--------------------------------------------------------------------------
	*/

	public static function get_dynamic_queries(): array {
		return [
			'storeengine_product_query'          => [ self::class, 'query_products' ],
			'storeengine_membership_group_query' => [ self::class, 'query_membership_groups' ],
		];
	}

	public static function query_products( $q = null ): array {
		$search = is_array( $q ) ? ( $q['search'] ?? '' ) : '';

		$posts = get_posts( [
			'post_type'      => 'storeengine_product',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
			's'              => $search,
		] );

		return array_map( function ( $post ) {
			return [ 'value' => $post->ID, 'label' => $post->post_title ];
		}, $posts );
	}

	public static function query_membership_groups( $q = null ): array {
		$posts = get_posts( [
			'post_type'      => 'storeengine_groups',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		return array_map( function ( $post ) {
			return [ 'value' => $post->ID, 'label' => $post->post_title ];
		}, $posts );
	}

	/*
	|--------------------------------------------------------------------------
	| ACTION DISPATCH
	|--------------------------------------------------------------------------
	*/

	public static function execute_node( array $node, array $input ): array {
		$event  = $node['data']['event'] ?? '';
		$config = $node['data']['config'] ?? [];
		$method = 'action_' . $event;

		if ( method_exists( static::class, $method ) ) {
			return static::$method( $config, $input );
		}

		return self::respond( $input );
	}

	private static function respond( array $data ): array {
		return [ 'port' => 'main', 'data' => $data ];
	}

	private static function action_error( string $message, array $input = [] ): array {
		return self::respond( array_merge( $input, [
			'success' => false,
			'error'   => $message,
		] ) );
	}

	private static function action_success( array $data, array $input = [] ): array {
		return self::respond( array_merge( $input, array_merge( [ 'success' => true ], $data ) ) );
	}

	/* ---------------- Order actions ---------------- */

	private static function action_update_order_status( array $config, array $input ): array {
		$order_id = absint( $config['order_id'] ?? 0 );
		$status   = sanitize_text_field( $config['order_status'] ?? '' );

		if ( ! $order_id || ! $status || ! function_exists( 'storeengine_get_order' ) ) {
			return self::action_error( 'A valid order ID and status are required.', $input );
		}

		$order = storeengine_get_order( $order_id );
		if ( ! $order || is_wp_error( $order ) ) {
			return self::action_error( 'Order not found.', $input );
		}

		try {
			$order->set_status( $status, (string) ( $config['note'] ?? '' ), true );
			$order->save();
		} catch ( \Throwable $e ) {
			return self::action_error( 'Failed to update status: ' . $e->getMessage(), $input );
		}

		return self::action_success( [ 'order_id' => $order_id, 'order_status' => $order->get_status() ], $input );
	}

	private static function action_add_order_note( array $config, array $input ): array {
		$order_id = absint( $config['order_id'] ?? 0 );
		$note     = (string) ( $config['note'] ?? '' );

		if ( ! $order_id || '' === trim( $note ) || ! function_exists( 'storeengine_get_order' ) ) {
			return self::action_error( 'A valid order ID and note are required.', $input );
		}

		$order = storeengine_get_order( $order_id );
		if ( ! $order || is_wp_error( $order ) ) {
			return self::action_error( 'Order not found.', $input );
		}

		$is_customer_note = ( 'customer' === ( $config['note_type'] ?? 'private' ) ) ? 1 : 0;
		$comment_id       = $order->add_order_note( $note, $is_customer_note );

		if ( ! $comment_id ) {
			return self::action_error( 'Failed to add order note.', $input );
		}

		return self::action_success( [ 'order_id' => $order_id, 'note_id' => $comment_id ], $input );
	}

	private static function action_refund_order( array $config, array $input ): array {
		$order_id = absint( $config['order_id'] ?? 0 );

		if ( ! $order_id || ! function_exists( 'storeengine_get_order' ) ) {
			return self::action_error( 'A valid order ID is required.', $input );
		}

		if ( ! class_exists( '\StoreEngine\Utils\Helper' ) || ! method_exists( '\StoreEngine\Utils\Helper', 'create_refund' ) ) {
			return self::action_error( 'StoreEngine refund API is unavailable.', $input );
		}

		$order = storeengine_get_order( $order_id );
		if ( ! $order || is_wp_error( $order ) ) {
			return self::action_error( 'Order not found.', $input );
		}

		$amount = $config['amount'] ?? '';
		if ( '' === $amount || null === $amount ) {
			$amount = method_exists( $order, 'get_remaining_refund_amount' ) ? $order->get_remaining_refund_amount() : $order->get_total();
		}

		$result = \StoreEngine\Utils\Helper::create_refund( [
			'order_id'      => $order_id,
			'amount'        => (float) $amount,
			'reason'        => (string) ( $config['reason'] ?? '' ),
			'restock_items' => 'yes' === ( $config['restock_items'] ?? 'no' ),
		] );

		if ( is_wp_error( $result ) ) {
			return self::action_error( $result->get_error_message(), $input );
		}

		return self::action_success( [ 'order_id' => $order_id, 'refunded_amount' => (float) $amount ], $input );
	}

	/* ---------------- Customer actions ---------------- */

	private static function action_create_customer( array $config, array $input ): array {
		$email = sanitize_email( $config['email'] ?? '' );

		if ( ! is_email( $email ) || ! class_exists( '\StoreEngine\Classes\Customer' ) ) {
			return self::action_error( 'A valid email is required.', $input );
		}

		if ( email_exists( $email ) ) {
			return self::action_error( 'A user with this email already exists.', $input );
		}

		try {
			$customer = new \StoreEngine\Classes\Customer();
			$customer->set_email( $email );
			self::apply_customer_fields( $customer, $config );
			$result = $customer->save();
		} catch ( \Throwable $e ) {
			return self::action_error( 'Failed to create customer: ' . $e->getMessage(), $input );
		}

		if ( is_wp_error( $result ) ) {
			return self::action_error( $result->get_error_message(), $input );
		}

		return self::action_success( [ 'customer_id' => $customer->get_id(), 'email' => $email ], $input );
	}

	private static function action_update_customer( array $config, array $input ): array {
		$user_id = absint( $config['customer_id'] ?? 0 );

		if ( ! $user_id || ! get_userdata( $user_id ) || ! class_exists( '\StoreEngine\Classes\Customer' ) ) {
			return self::action_error( 'A valid customer (user) ID is required.', $input );
		}

		try {
			$customer = new \StoreEngine\Classes\Customer( $user_id );
			self::apply_customer_fields( $customer, $config );
			$customer->save();
		} catch ( \Throwable $e ) {
			return self::action_error( 'Failed to update customer: ' . $e->getMessage(), $input );
		}

		return self::action_success( [ 'customer_id' => $user_id ], $input );
	}

	private static function apply_customer_fields( $customer, array $config ): void {
		$map = [
			'first_name'      => 'set_first_name',
			'last_name'       => 'set_last_name',
			'phone'           => 'set_billing_phone',
			'billing_city'    => 'set_billing_city',
			'billing_country' => 'set_billing_country',
		];

		foreach ( $map as $key => $setter ) {
			if ( isset( $config[ $key ] ) && '' !== $config[ $key ] && method_exists( $customer, $setter ) ) {
				$customer->{$setter}( sanitize_text_field( $config[ $key ] ) );
			}
		}
	}

	/* ---------------- Product / inventory actions ---------------- */

	private static function action_adjust_stock( array $config, array $input ): array {
		$product_id = absint( $config['product_id'] ?? 0 );
		$mode       = $config['mode'] ?? 'set';
		$quantity   = (int) ( $config['quantity'] ?? 0 );

		$product = self::get_product_object( $product_id );
		if ( ! $product ) {
			return self::action_error( 'Product not found.', $input );
		}

		if ( ! method_exists( $product, 'set_stock_quantity' ) ) {
			return self::action_error( 'This product does not support stock management.', $input );
		}

		$current = method_exists( $product, 'get_stock_quantity' ) ? (int) $product->get_stock_quantity() : 0;

		switch ( $mode ) {
			case 'increase':
				$new = $current + $quantity;
				break;
			case 'decrease':
				$new = max( 0, $current - $quantity );
				break;
			default:
				$new = $quantity;
		}

		try {
			$product->set_stock_quantity( $new );
			$product->save();
		} catch ( \Throwable $e ) {
			return self::action_error( 'Failed to adjust stock: ' . $e->getMessage(), $input );
		}

		return self::action_success( [ 'product_id' => $product_id, 'stock_quantity' => $new ], $input );
	}

	private static function action_set_stock_status( array $config, array $input ): array {
		$product_id = absint( $config['product_id'] ?? 0 );
		$status     = sanitize_text_field( $config['stock_status'] ?? '' );

		$product = self::get_product_object( $product_id );
		if ( ! $product ) {
			return self::action_error( 'Product not found.', $input );
		}

		if ( ! method_exists( $product, 'set_stock_status' ) ) {
			return self::action_error( 'This product does not support stock status.', $input );
		}

		try {
			$product->set_stock_status( $status );
			$product->save();
		} catch ( \Throwable $e ) {
			return self::action_error( 'Failed to set stock status: ' . $e->getMessage(), $input );
		}

		return self::action_success( [ 'product_id' => $product_id, 'stock_status' => $status ], $input );
	}

	private static function get_product_object( int $product_id ) {
		if ( ! $product_id ) {
			return null;
		}

		if ( function_exists( 'storeengine_get_product' ) ) {
			$product = storeengine_get_product( $product_id );
			if ( $product && ! is_wp_error( $product ) ) {
				return $product;
			}
		}

		if ( class_exists( '\StoreEngine\Classes\ProductFactory' ) ) {
			$factory = new \StoreEngine\Classes\ProductFactory();
			$product = $factory->get_product( $product_id );
			return $product ?: null;
		}

		return null;
	}

	/* ---------------- Coupon actions ---------------- */

	private static function action_create_coupon( array $config, array $input ): array {
		$code = sanitize_text_field( $config['code'] ?? '' );

		if ( '' === $code || ! class_exists( '\StoreEngine\Utils\Helper' ) ) {
			return self::action_error( 'A coupon code is required.', $input );
		}

		$post_type = \StoreEngine\Utils\Helper::COUPON_POST_TYPE;

		$post_id = wp_insert_post( [
			'post_title'  => $code,
			'post_type'   => $post_type,
			'post_status' => 'publish',
		] );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return self::action_error( 'Failed to create coupon.', $input );
		}

		update_post_meta( $post_id, '_storeengine_coupon_name', $code );
		update_post_meta( $post_id, '_storeengine_coupon_type', sanitize_text_field( $config['coupon_type'] ?? 'percentage' ) );
		update_post_meta( $post_id, '_storeengine_coupon_amount', (float) ( $config['amount'] ?? 0 ) );

		if ( isset( $config['usage_limit'] ) && '' !== $config['usage_limit'] ) {
			update_post_meta( $post_id, '_storeengine_coupon_usage_limit', absint( $config['usage_limit'] ) );
		}

		return self::action_success( [ 'coupon_id' => $post_id, 'code' => $code ], $input );
	}

	/* ---------------- Subscription actions (addon) ---------------- */

	private static function action_update_subscription_status( array $config, array $input ): array {
		$subscription_id = absint( $config['subscription_id'] ?? 0 );
		$status          = sanitize_text_field( $config['subscription_status'] ?? '' );

		if ( ! $subscription_id || ! $status || ! class_exists( '\StoreEngine\Addons\Subscription\Classes\Subscription' ) ) {
			return self::action_error( 'A valid subscription ID and status are required.', $input );
		}

		try {
			$subscription = new \StoreEngine\Addons\Subscription\Classes\Subscription( $subscription_id );
			$subscription->set_status( $status, '', true );
			$subscription->save();
		} catch ( \Throwable $e ) {
			return self::action_error( 'Failed to update subscription: ' . $e->getMessage(), $input );
		}

		return self::action_success( [ 'subscription_id' => $subscription_id, 'status' => $subscription->get_status() ], $input );
	}

	/* ---------------- Membership actions (addon) ---------------- */

	private static function action_grant_membership( array $config, array $input ): array {
		$user_id  = absint( $config['user_id'] ?? 0 );
		$group_id = absint( $config['group_id'] ?? 0 );

		if ( ! $user_id || ! get_userdata( $user_id ) || ! $group_id ) {
			return self::action_error( 'A valid user ID and membership group are required.', $input );
		}

		$meta_key = '_storeengine_user_membership_data';
		$data     = get_user_meta( $user_id, $meta_key, true );
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		$expiration = sanitize_text_field( $config['expiration_date'] ?? '' );

		$data[ $group_id ] = [
			'expiration_date' => [
				'is_enable_expiration' => $expiration ? 1 : 0,
				'specific_date'        => $expiration,
			],
		];

		update_user_meta( $user_id, $meta_key, $data );

		return self::action_success( [ 'user_id' => $user_id, 'group_id' => $group_id ], $input );
	}

	private static function action_revoke_membership( array $config, array $input ): array {
		$user_id  = absint( $config['user_id'] ?? 0 );
		$group_id = absint( $config['group_id'] ?? 0 );

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return self::action_error( 'A valid user ID is required.', $input );
		}

		$meta_key = '_storeengine_user_membership_data';

		if ( ! $group_id ) {
			delete_user_meta( $user_id, $meta_key );
			return self::action_success( [ 'user_id' => $user_id, 'revoked' => 'all' ], $input );
		}

		$data = get_user_meta( $user_id, $meta_key, true );
		if ( is_array( $data ) && isset( $data[ $group_id ] ) ) {
			unset( $data[ $group_id ] );
			update_user_meta( $user_id, $meta_key, $data );
		}

		return self::action_success( [ 'user_id' => $user_id, 'group_id' => $group_id ], $input );
	}
}
