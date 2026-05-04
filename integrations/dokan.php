<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Dokan\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dokan extends IntegrationBase {

	use Helper;

	public static function get_slug(): string {
		return 'dokan';
	}

	public static function get_name(): string {
		return 'Dokan';
	}

	public static function get_icon(): string {
		return 'dokan.svg';
	}

	public static function get_triggers(): array {
		return [
			'new_seller_created' => [
				'label' => 'New Seller Created',
				'hook' => 'dokan_new_seller_created',
			],
			'store_profile_saved' => [
				'label' => 'Store Profile Saved',
				'hook' => 'dokan_store_profile_saved',
			],
			'new_product_added' => [
				'label' => 'New Product Added',
				'hook' => 'dokan_new_product_added',
			],
			'product_updated' => [
				'label' => 'Product Updated',
				'hook' => 'dokan_product_updated',
			],
			'product_deleted' => [
				'label' => 'Product Deleted',
				'hook' => 'dokan_product_deleted',
			],
			'checkout_update_order_meta' => [
				'label' => 'Checkout Order Meta Updated',
				'hook' => 'dokan_checkout_update_order_meta',
			],
			'vendor_enabled' => [
				'label' => 'Vendor Enabled',
				'hook' => 'dokan_vendor_enabled',
			],
			'vendor_disabled' => [
				'label' => 'Vendor Disabled',
				'hook' => 'dokan_vendor_disabled',
			],
			'withdraw_request_created' => [
				'label' => 'Withdraw Request Created',
				'hook' => 'dokan_after_withdraw_request',
			],
			'withdraw_created' => [
				'label' => 'Withdraw Created',
				'hook' => 'dokan_withdraw_created',
			],
			'withdraw_request_pending' => [
				'label' => 'Withdraw Request Pending',
				'hook' => 'dokan_withdraw_request_pending',
			],
			'withdraw_request_approved' => [
				'label' => 'Withdraw Request Approved',
				'hook' => 'dokan_withdraw_request_approved',
			],
			'withdraw_request_cancelled' => [
				'label' => 'Withdraw Request Cancelled',
				'hook' => 'dokan_withdraw_request_cancelled',
			],
			'withdraw_status_updated' => [
				'label' => 'Withdraw Status Updated',
				'hook' => 'dokan_withdraw_status_updated',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		$vendor_selector = [
			[
				'key'      => 'vendor_id',
				'label'    => 'Vendor',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'dokan',
					'query'       => 'vendors',
					'select'      => [ 'name', 'label' ],
				],
				'required' => false,
			],
		];

		$product_selector = [
			[
				'key'      => 'product_id',
				'label'    => 'Product',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'dokan',
					'query'       => 'products',
					'select'      => [ 'name', 'label' ],
				],
				'required' => false,
			],
		];

		if ( in_array( $trigger, [ 'new_product_added', 'product_updated', 'product_deleted' ], true ) ) {
			return array_merge( $vendor_selector, $product_selector );
		}

		if ( in_array(
			$trigger,
			[
				'new_seller_created',
				'store_profile_saved',
				'checkout_update_order_meta',
				'vendor_enabled',
				'vendor_disabled',
				'withdraw_request_created',
				'withdraw_created',
				'withdraw_request_pending',
				'withdraw_request_approved',
				'withdraw_request_cancelled',
				'withdraw_status_updated',
			],
			true
		) ) {
			return $vendor_selector;
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		if ( ! self::is_dokan_available() ) {
			return false;
		}

		$event  = self::resolve_node_event( $node, 'trigger' );
		$config = self::resolve_node_config( $node );
		if ( '' === $event ) {
			return false;
		}

		switch ( $event ) {
			case 'new_seller_created':
			case 'store_profile_saved':
			case 'vendor_enabled':
			case 'vendor_disabled':
				$vendor_id = self::parse_positive_int( $args[0] ?? 0 );
				if ( $vendor_id <= 0 || ! self::matches_id_filter( $config, 'vendor_id', $vendor_id ) ) {
					return false;
				}

				return [
					'event'         => $event,
					'event_time'    => current_time( 'mysql' ),
					'vendor_id'     => $vendor_id,
					'vendor'        => self::build_vendor_payload_from_id( $vendor_id ),
					'store_info'    => is_array( $args[1] ?? null ) ? $args[1] : [],
					'previous_store'=> is_array( $args[2] ?? null ) ? $args[2] : [],
				];

			case 'new_product_added':
			case 'product_updated':
			case 'product_deleted':
				$product_id = self::parse_positive_int( $args[0] ?? 0 );
				if ( $product_id <= 0 || ! self::matches_id_filter( $config, 'product_id', $product_id ) ) {
					return false;
				}

				$product_data = is_array( $args[1] ?? null ) ? $args[1] : [];
				$product      = self::build_product_payload(
					$product_id,
					[
						'input' => $product_data,
					]
				);
				$vendor_id    = self::parse_positive_int( $product_data['post_author'] ?? ( $product['vendor_id'] ?? 0 ) );
				if ( $vendor_id > 0 ) {
					$product['vendor_id'] = $vendor_id;
					$product['vendor']    = self::build_vendor_payload_from_id( $vendor_id );
				}

				if ( $vendor_id > 0 && ! self::matches_id_filter( $config, 'vendor_id', $vendor_id ) ) {
					return false;
				}

				return [
					'event'      => $event,
					'event_time' => current_time( 'mysql' ),
					'product_id' => $product_id,
					'vendor_id'  => $vendor_id,
					'product'    => $product,
				];

			case 'checkout_update_order_meta':
				$order_id  = self::parse_positive_int( $args[0] ?? 0 );
				$vendor_id = self::parse_positive_int( $args[1] ?? 0 );

				if ( $order_id <= 0 ) {
					return false;
				}

				if ( $vendor_id <= 0 && function_exists( 'dokan_get_seller_id_by_order' ) ) {
					$vendor_id = self::parse_positive_int( dokan_get_seller_id_by_order( $order_id ) );
				}

				if ( $vendor_id > 0 && ! self::matches_id_filter( $config, 'vendor_id', $vendor_id ) ) {
					return false;
				}

				return [
					'event'      => $event,
					'event_time' => current_time( 'mysql' ),
					'order_id'   => $order_id,
					'vendor_id'  => $vendor_id,
					'order'      => self::build_order_payload( $order_id, $vendor_id ),
				];

			case 'withdraw_request_created':
				$vendor_id   = self::parse_positive_int( $args[0] ?? 0 );
				$amount      = isset( $args[1] ) ? (float) $args[1] : 0.0;
				$method      = (string) ( $args[2] ?? '' );
				$withdraw_id = self::parse_positive_int( $args[3] ?? 0 );
				$withdraw    = self::resolve_withdraw_entity( $withdraw_id );
				if ( $withdraw ) {
					$withdraw_payload = self::build_withdraw_payload( $withdraw );
					if ( $vendor_id <= 0 ) {
						$vendor_id = self::parse_positive_int( $withdraw_payload['vendor_id'] ?? 0 );
					}

					if ( $withdraw_id <= 0 ) {
						$withdraw_id = self::parse_positive_int( $withdraw_payload['withdraw_id'] ?? 0 );
					}
				}

				if ( $vendor_id <= 0 || ! self::matches_id_filter( $config, 'vendor_id', $vendor_id ) ) {
					return false;
				}

				return [
					'event'       => $event,
					'event_time'  => current_time( 'mysql' ),
					'vendor_id'   => $vendor_id,
					'withdraw_id' => $withdraw_id,
					'amount'      => $amount,
					'method'      => $method,
					'vendor'      => self::build_vendor_payload_from_id( $vendor_id ),
					'withdraw'    => $withdraw ? self::build_withdraw_payload( $withdraw ) : [],
				];

			case 'withdraw_created':
			case 'withdraw_request_pending':
			case 'withdraw_request_approved':
			case 'withdraw_request_cancelled':
				$withdraw = self::resolve_withdraw_entity( $args[0] ?? null );
				if ( ! $withdraw ) {
					$withdraw = self::resolve_withdraw_entity( $args[2] ?? null );
				}

				if ( ! $withdraw ) {
					$withdraw = self::resolve_withdraw_entity( $args[1] ?? null );
				}

				if ( ! $withdraw ) {
					return false;
				}

				$withdraw_payload = self::build_withdraw_payload( $withdraw );
				$vendor_id        = (int) ( $withdraw_payload['vendor_id'] ?? 0 );
				if ( $vendor_id > 0 && ! self::matches_id_filter( $config, 'vendor_id', $vendor_id ) ) {
					return false;
				}

				return [
					'event'       => $event,
					'event_time'  => current_time( 'mysql' ),
					'withdraw_id' => (int) ( $withdraw_payload['withdraw_id'] ?? 0 ),
					'vendor_id'   => $vendor_id,
					'withdraw'    => $withdraw_payload,
				];

			case 'withdraw_status_updated':
				$status      = $args[0] ?? '';
				$vendor_id   = self::parse_positive_int( $args[1] ?? 0 );
				$withdraw_id = self::parse_positive_int( $args[2] ?? 0 );
				$withdraw    = self::resolve_withdraw_entity( $withdraw_id );
				if ( ! $withdraw ) {
					$withdraw = self::resolve_withdraw_entity( $args[0] ?? null );
				}

				$withdraw_payload = $withdraw ? self::build_withdraw_payload( $withdraw ) : [];
				if ( $vendor_id <= 0 ) {
					$vendor_id = self::parse_positive_int( $withdraw_payload['vendor_id'] ?? 0 );
				}

				if ( $withdraw_id <= 0 ) {
					$withdraw_id = self::parse_positive_int( $withdraw_payload['withdraw_id'] ?? 0 );
				}

				if ( $vendor_id <= 0 || ! self::matches_id_filter( $config, 'vendor_id', $vendor_id ) ) {
					return false;
				}

				return [
					'event'           => $event,
					'event_time'      => current_time( 'mysql' ),
					'vendor_id'       => $vendor_id,
					'withdraw_id'     => $withdraw_id,
					'withdraw_status' => self::normalize_withdraw_status( $status ),
					'withdraw'        => $withdraw_payload,
				];
		}//end switch

		return false;
	}

	public static function get_actions(): array {
		return [
			'get_vendor_single' => [
				'label' => 'Get Vendor (Single)',
			],
			'get_vendors_all' => [
				'label' => 'Get Vendors (All)',
			],
			'get_withdraw_single' => [
				'label' => 'Get Withdraw (Single)',
			],
			'get_withdraws_all' => [
				'label' => 'Get Withdraws (All)',
			],
			'add_action' => [
				'label' => 'Add Action Hook',
			],
			'do_action' => [
				'label' => 'Do Action Hook',
			],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
			'get_vendor_single' => [
				[
					'key'      => 'vendor_id',
					'label'    => 'Vendor',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'dokan',
						'query'       => 'vendors',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			],
			'get_vendors_all' => [
				[
					'key'     => 'limit',
					'label'   => 'Limit',
					'type'    => 'number',
					'default' => 20,
				],
				[
					'key'     => 'page',
					'label'   => 'Page',
					'type'    => 'number',
					'default' => 1,
				],
				[
					'key'   => 'search',
					'label' => 'Search',
					'type'  => 'text',
				],
			],
			'get_withdraw_single' => [
				[
					'key'      => 'withdraw_id',
					'label'    => 'Withdraw',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'dokan',
						'query'       => 'withdraws',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			],
			'get_withdraws_all' => [
				[
					'key'      => 'vendor_id',
					'label'    => 'Vendor',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'dokan',
						'query'       => 'vendors',
						'select'      => [ 'name', 'label' ],
					],
					'required' => false,
				],
				[
					'key'     => 'withdraw_status',
					'label'   => 'Withdraw Status',
					'type'    => 'select',
					'options' => [
						[
							'label' => 'Any',
							'value' => 'any',
						],
						[
							'label' => 'Pending',
							'value' => 'pending',
						],
						[
							'label' => 'Approved',
							'value' => 'approved',
						],
						[
							'label' => 'Cancelled',
							'value' => 'cancelled',
						],
					],
				],
				[
					'key'     => 'limit',
					'label'   => 'Limit',
					'type'    => 'number',
					'default' => 20,
				],
				[
					'key'     => 'page',
					'label'   => 'Page',
					'type'    => 'number',
					'default' => 1,
				],
			],
			'add_action' => [
				[
					'key'      => 'hook_name',
					'label'    => 'Hook Name',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'   => 'accepted_args',
					'label' => 'Accepted Args',
					'type'  => 'number',
				],
			],
			'do_action' => [
				[
					'key'      => 'hook_name',
					'label'    => 'Hook Name',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'   => 'arg_1',
					'label' => 'Argument 1',
					'type'  => 'expression',
				],
				[
					'key'   => 'arg_2',
					'label' => 'Argument 2',
					'type'  => 'expression',
				],
			],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = self::resolve_node_event( $node, 'action' );
		$config = self::resolve_node_config( $node );

		if ( ! self::is_dokan_available() && ! in_array( $event, [ 'add_action', 'do_action' ], true ) ) {
			return self::error_response( 'Dokan is not available', $input );
		}

		switch ( $event ) {
			case 'get_vendor_single':
				return self::action_get_vendor_single( $config, $input );
			case 'get_vendors_all':
				return self::action_get_vendors_all( $config, $input );
			case 'get_withdraw_single':
				return self::action_get_withdraw_single( $config, $input );
			case 'get_withdraws_all':
				return self::action_get_withdraws_all( $config, $input );
			case 'add_action':
				return self::action_add_action( $config, $input );
			case 'do_action':
				return self::action_do_action( $config, $input );
		}//end switch

		return self::main_response( $input );
	}

	public static function get_dynamic_queries(): array {
		return [
			'vendors' => [ self::class, 'vendors_query' ],
			'products' => [ self::class, 'products_query' ],
			'withdraws' => [ self::class, 'withdraws_query' ],
		];
	}

	private static function is_dokan_available(): bool {
		return function_exists( 'dokan' ) || function_exists( 'dokan_get_store_info' );
	}
}
