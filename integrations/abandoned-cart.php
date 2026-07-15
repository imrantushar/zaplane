<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GemCrm\Addons\AbandonedCart\Database\Models\AbandonedCart' ) ) {
	return;
}

use GemCrm\Addons\AbandonedCart\Database\Models\AbandonedCart as AbandonedCartModel;

class AbandonedCart extends IntegrationBase {

	public static function get_slug(): string {
		return 'abandoned-cart';
	}

	public static function get_name(): string {
		return 'Abandoned Cart';
	}

	public static function get_icon(): string {
		return 'abandoned-cart.svg';
	}

	public static function get_triggers(): array {
		return [
			'cart_abandoned' => [
				'label' => 'Cart Abandoned',
				'hook'  => 'zaplane/abandoned_cart/started',
			],
			'cart_recovered' => [
				'label' => 'Cart Recovered',
				'hook'  => 'zaplane/abandoned_cart/recovered',
			],
			'cart_lost' => [
				'label' => 'Cart Lost',
				'hook'  => 'zaplane/abandoned_cart/lost',
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$cart = $args[0] ?? null;

		if ( ! $cart ) {
			return false;
		}

		if ( $cart instanceof AbandonedCartModel ) {
			return self::cart_payload( $cart );
		}

		if ( is_array( $cart ) ) {
			return $cart;
		}

		return false;
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		$base = [
			'id'            => 1,
			'full_name'     => 'John Doe',
			'email'         => 'john@example.com',
			'status'        => 'processing',
			'total'         => 99.99,
			'subtotal'      => 89.99,
			'shipping'      => 5.00,
			'tax'           => 5.00,
			'discounts'     => 0.00,
			'fees'          => 0.00,
			'currency'      => 'USD',
			'checkout_key'  => 'abc123uuid',
			'recovery_link' => home_url( '/?zaplane=1&route=abandoned-cart&checkout_key=abc123uuid' ),
			'contact_id'    => 42,
			'order_id'      => null,
			'click_counts'  => 0,
			'provider'      => 'woo',
			'user_id'       => 5,
			'abandoned_at'  => '2026-01-01 10:00:00',
			'recovered_at'  => null,
			'created_at'    => '2026-01-01 09:30:00',
		];

		if ( 'cart_recovered' === $trigger ) {
			$base['status']       = 'recovered';
			$base['recovered_at'] = '2026-01-01 11:00:00';
			$base['order_id']     = 123;
		}

		if ( 'cart_lost' === $trigger ) {
			$base['status'] = 'lost';
		}

		return $base;
	}

	public static function get_actions(): array {
		return [
			'get_cart'           => [ 'label' => 'Get Cart by ID' ],
			'get_cart_by_email'  => [ 'label' => 'Get Cart by Email' ],
			'get_carts'          => [ 'label' => 'Get Abandoned Carts List' ],
			'update_cart_status' => [ 'label' => 'Update Cart Status' ],
			'get_report'         => [ 'label' => 'Get Abandoned Cart Report' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$status_options = [
			[
				'label' => 'Draft',
				'value' => 'draft'
			],
			[
				'label' => 'Processing',
				'value' => 'processing'
			],
			[
				'label' => 'Recovered',
				'value' => 'recovered'
			],
			[
				'label' => 'Lost',
				'value' => 'lost'
			],
			[
				'label' => 'Opt Out',
				'value' => 'opt_out'
			],
			[
				'label' => 'Skipped',
				'value' => 'skipped'
			],
		];

		switch ( $action ) {
			case 'get_cart':
				return [
					[
						'key'      => 'cart_id',
						'label'    => 'Cart ID',
						'type'     => 'text',
						'required' => true,
					],
				];
			case 'get_cart_by_email':
				return [
					[
						'key'      => 'email',
						'label'    => 'Email Address',
						'type'     => 'email',
						'required' => true,
					],
				];
			case 'get_carts':
				return [
					[
						'key'     => 'status',
						'label'   => 'Status',
						'type'    => 'select',
						'options' => $status_options,
					],
					[
						'key'     => 'limit',
						'label'   => 'Limit',
						'type'    => 'number',
						'default' => 20,
					],
				];
			case 'update_cart_status':
				return [
					[
						'key'      => 'cart_id',
						'label'    => 'Cart ID',
						'type'     => 'text',
						'required' => true,
					],
					[
						'key'      => 'status',
						'label'    => 'New Status',
						'type'     => 'select',
						'options'  => $status_options,
						'required' => true,
					],
				];
			case 'get_report':
				return [
					[
						'key'   => 'date_from',
						'label' => 'Date From (YYYY-MM-DD)',
						'type'  => 'text',
					],
					[
						'key'   => 'date_to',
						'label' => 'Date To (YYYY-MM-DD)',
						'type'  => 'text',
					],
				];
			default:
				return [];
		}//end switch
	}

	public static function execute_node( array $node, array $input ): array {
		$action = self::get_node_action( $node );
		$config = self::get_node_config( $node );

		switch ( $action ) {
			case 'get_cart':
				return self::action_get_cart( $config, $input );
			case 'get_cart_by_email':
				return self::action_get_cart_by_email( $config, $input );
			case 'get_carts':
				return self::action_get_carts( $config, $input );
			case 'update_cart_status':
				return self::action_update_cart_status( $config, $input );
			case 'get_report':
				return self::action_get_report( $config, $input );
			default:
				return [
					'port' => 'main',
					'data' => $input
				];
		}
	}

	// --- Private action handlers ---

	private static function action_get_cart( array $config, array $input ): array {
		$cart_id = (int) ( $config['cart_id'] ?? 0 );
		if ( ! $cart_id ) {
			return self::respond_error( 'cart_id is required' );
		}
		$cart = AbandonedCartModel::find( $cart_id );
		if ( ! $cart ) {
			return self::respond_error( 'Cart not found' );
		}
		return self::respond( self::cart_payload( $cart ) );
	}

	private static function action_get_cart_by_email( array $config, array $input ): array {
		$email = sanitize_email( $config['email'] ?? '' );
		if ( ! $email ) {
			return self::respond_error( 'email is required' );
		}
		$cart = AbandonedCartModel::where( 'email', $email )
			->orderBy( 'id', 'desc' )
			->first();
		if ( ! $cart ) {
			return self::respond_error( 'No cart found for that email' );
		}
		return self::respond( self::cart_payload( $cart ) );
	}

	private static function action_get_carts( array $config, array $input ): array {
		$status = sanitize_text_field( $config['status'] ?? '' );
		$limit  = max( 1, min( 100, (int) ( $config['limit'] ?? 20 ) ) );

		$query = AbandonedCartModel::orderBy( 'id', 'desc' );

		if ( $status ) {
			$query = AbandonedCartModel::where( 'status', $status )->orderBy( 'id', 'desc' );
		}

		$carts = $query->limit( $limit )->get();
		$items = [];

		foreach ( $carts as $cart ) {
			$items[] = self::cart_payload( $cart );
		}

		return self::respond( [
			'carts' => $items,
			'count' => count( $items )
		] );
	}

	private static function action_update_cart_status( array $config, array $input ): array {
		$cart_id = (int) ( $config['cart_id'] ?? 0 );
		$status  = sanitize_text_field( $config['status'] ?? '' );

		if ( ! $cart_id || ! $status ) {
			return self::respond_error( 'cart_id and status are required' );
		}

		$allowed = [ 'draft', 'processing', 'recovered', 'lost', 'opt_out', 'skipped' ];
		if ( ! in_array( $status, $allowed, true ) ) {
			return self::respond_error( 'Invalid status value' );
		}

		$update = [
			'status' => $status,
			'updated_at' => current_time( 'mysql' )
		];
		if ( 'recovered' === $status ) {
			$update['recovered_at'] = current_time( 'mysql' );
		}

		AbandonedCartModel::where( 'id', $cart_id )->update( $update );

		return self::respond( [
			'cart_id' => $cart_id,
			'status' => $status,
			'updated' => true
		] );
	}

	private static function action_get_report( array $config, array $input ): array {
		global $wpdb;

		$table  = $wpdb->prefix . 'gemcrm_abandoned_cart';
		$from   = sanitize_text_field( $config['date_from'] ?? '' );
		$to     = sanitize_text_field( $config['date_to'] ?? '' );
		$where  = '1=1';
		$params = [];

		if ( $from ) {
			$where   .= ' AND created_at >= %s';
			$params[] = $from . ' 00:00:00';
		}
		if ( $to ) {
			$where   .= ' AND created_at <= %s';
			$params[] = $to . ' 23:59:59';
		}

		$statuses = [ 'recovered', 'processing', 'lost', 'draft', 'opt_out' ];
		$summary  = [];

		foreach ( $statuses as $st ) {
			$st_where  = $where . ' AND status = %s';
			$st_params = array_merge( $params, [ $st ] );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row            = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as revenue FROM {$table} WHERE {$st_where}", ...$st_params ), ARRAY_A );
			$summary[ $st ] = [
				'count'   => (int) ( $row['cnt'] ?? 0 ),
				'revenue' => (float) ( $row['revenue'] ?? 0 ),
			];
		}

		$recovered     = $summary['recovered']['count'];
		$total_all     = array_sum( array_column( $summary, 'count' ) );
		$recovery_rate = $total_all > 0 ? round( ( $recovered / $total_all ) * 100, 2 ) : 0;

		return self::respond( [
			'summary'       => $summary,
			'recovery_rate' => $recovery_rate,
			'total_carts'   => $total_all,
			'date_range'    => [
				'from' => $from,
				'to' => $to
			],
		] );
	}

	// --- Helpers ---

	private static function cart_payload( AbandonedCartModel $cart ): array {
		return [
			'id'            => $cart->id,
			'full_name'     => $cart->full_name,
			'email'         => $cart->email,
			'status'        => $cart->status,
			'total'         => $cart->total,
			'subtotal'      => $cart->subtotal,
			'shipping'      => $cart->shipping,
			'tax'           => $cart->tax,
			'discounts'     => $cart->discounts,
			'fees'          => $cart->fees,
			'currency'      => $cart->currency,
			'checkout_key'  => $cart->checkout_key,
			'recovery_link' => add_query_arg(
				[
					'zaplane'      => '1',
					'route'        => 'abandoned-cart',
					'checkout_key' => $cart->checkout_key,
				],
				home_url( '/' )
			),
			'cart_hash'     => $cart->cart_hash,
			'is_optout'     => $cart->is_optout,
			'user_id'       => $cart->user_id,
			'contact_id'    => $cart->contact_id,
			'order_id'      => $cart->order_id,
			'click_counts'  => $cart->click_counts,
			'note'          => $cart->note,
			'provider'      => $cart->provider,
			'cart'          => $cart->get_cart_contents(),
			'abandoned_at'  => $cart->abandoned_at,
			'recovered_at'  => $cart->recovered_at,
			'created_at'    => $cart->created_at,
			'updated_at'    => $cart->updated_at,
		];
	}

	private static function get_node_action( array $node ): string {
		return $node['data']['event'] ?? $node['config']['action'] ?? $node['data']['action'] ?? '';
	}

	private static function get_node_config( array $node ): array {
		if ( ! empty( $node['data']['config'] ) && is_array( $node['data']['config'] ) ) {
			return $node['data']['config'];
		}
		if ( ! empty( $node['config']['data'] ) && is_array( $node['config']['data'] ) ) {
			return $node['config']['data'];
		}
		if ( ! empty( $node['config'] ) && is_array( $node['config'] ) ) {
			return $node['config'];
		}
		return [];
	}

	private static function respond( array $data, string $port = 'main' ): array {
		return [
			'port' => $port,
			'data' => $data
		];
	}

	private static function respond_error( string $message ): array {
		return self::respond( [ 'error' => $message ], 'error' );
	}
}
