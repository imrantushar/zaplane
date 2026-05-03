<?php
namespace Zaplane\Integrations\Dokan;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Helper {

	private static function resolve_node_event( array $node, string $type ): string {
		$candidates = [
			$node['event'] ?? null,
			$node['data']['event'] ?? null,
			$node[ $type ] ?? null,
			$node['data'][ $type ] ?? null,
			$node['config']['event'] ?? null,
			$node['config'][ $type ] ?? null,
		];

		foreach ( $candidates as $candidate ) {
			if ( ! is_scalar( $candidate ) ) {
				continue;
			}

			$event = trim( (string) $candidate );
			if ( '' !== $event ) {
				return $event;
			}
		}

		return '';
	}

	private static function resolve_node_config( array $node ): array {
		if ( isset( $node['data']['config'] ) && is_array( $node['data']['config'] ) ) {
			return $node['data']['config'];
		}

		if ( isset( $node['config']['data'] ) && is_array( $node['config']['data'] ) ) {
			return $node['config']['data'];
		}

		if ( isset( $node['config']['config'] ) && is_array( $node['config']['config'] ) ) {
			return $node['config']['config'];
		}

		if ( isset( $node['config'] ) && is_array( $node['config'] ) ) {
			return self::strip_structural_node_keys( $node['config'] );
		}

		return self::strip_structural_node_keys( $node );
	}

	private static function strip_structural_node_keys( array $config ): array {
		unset( $config['app'], $config['event'], $config['action'], $config['trigger'], $config['type'], $config['hook'], $config['label'], $config['connection_id'], $config['data'] );
		return $config;
	}

	private static function action_get_vendor_single( array $config, array $input ): array {
		$vendor_id = self::parse_positive_int( $config['vendor_id'] ?? 0 );
		if ( $vendor_id <= 0 ) {
			return self::error_response( 'Vendor ID is required', $input );
		}

		$vendor = self::build_vendor_payload_from_id( $vendor_id );
		if ( empty( $vendor ) ) {
			return self::error_response( 'Vendor not found', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'vendor' => $vendor,
				]
			)
		);
	}

	private static function action_get_vendors_all( array $config, array $input ): array {
		$limit  = max( 1, (int) ( $config['limit'] ?? 20 ) );
		$page   = max( 1, (int) ( $config['page'] ?? 1 ) );
		$search = trim( (string) ( $config['search'] ?? '' ) );

		$items = [];
		$total = 0;
		if ( function_exists( 'dokan_get_sellers' ) ) {
			$result = dokan_get_sellers(
				[
					'number' => $limit,
					'paged'  => $page,
					'search' => $search,
				]
			);

			$users = is_array( $result ) ? ( $result['users'] ?? [] ) : [];
			foreach ( $users as $user ) {
				$vendor_id = self::parse_positive_int( $user );
				if ( $vendor_id <= 0 ) {
					continue;
				}

				$items[] = self::build_vendor_payload_from_id( $vendor_id );
			}

			$total = (int) ( $result['count'] ?? count( $items ) );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'items' => $items,
					'total' => $total,
					'limit' => $limit,
					'page'  => $page,
				]
			)
		);
	}

	private static function action_get_withdraw_single( array $config, array $input ): array {
		$withdraw_id = self::parse_positive_int( $config['withdraw_id'] ?? 0 );
		if ( $withdraw_id <= 0 ) {
			return self::error_response( 'Withdraw ID is required', $input );
		}

		$withdraw = self::resolve_withdraw_entity( $withdraw_id );
		if ( ! $withdraw ) {
			return self::error_response( 'Withdraw not found', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'withdraw' => self::build_withdraw_payload( $withdraw ),
				]
			)
		);
	}

	private static function action_get_withdraws_all( array $config, array $input ): array {
		$limit     = max( 1, (int) ( $config['limit'] ?? 20 ) );
		$page      = max( 1, (int) ( $config['page'] ?? 1 ) );
		$vendor_id = self::parse_positive_int( $config['vendor_id'] ?? 0 );
		$status    = self::normalize_withdraw_status( $config['withdraw_status'] ?? ( $config['status'] ?? 'any' ) );

		$args = [
			'limit'  => $limit,
			'offset' => ( $page - 1 ) * $limit,
		];

		if ( $vendor_id > 0 ) {
			$args['user_id'] = $vendor_id;
		}

		if ( '' !== $status && 'any' !== $status ) {
			$args['status'] = self::resolve_withdraw_status_code( $status );
		}

		$items = [];
		foreach ( self::resolve_withdraw_collection( $args ) as $withdraw ) {
			$payload = self::build_withdraw_payload( $withdraw );
			if ( empty( $payload ) ) {
				continue;
			}

			$payload_vendor_id = (int) ( $payload['vendor_id'] ?? 0 );
			if ( $vendor_id > 0 && $payload_vendor_id !== $vendor_id ) {
				continue;
			}

			$payload_status = (string) ( $payload['status'] ?? '' );
			if ( '' !== $status && 'any' !== $status && $status !== $payload_status ) {
				continue;
			}

			$items[] = $payload;
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'items' => $items,
					'total' => count( $items ),
					'limit' => $limit,
					'page'  => $page,
				]
			)
		);
	}

	private static function action_add_action( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::error_response( 'Hook name is required', $input );
		}

		$accepted_args = (int) ( $config['accepted_args'] ?? 1 );
		$accepted_args = min( 99, max( 1, $accepted_args ) );

		add_action(
			$hook_name,
			static function () {
			},
			10,
			$accepted_args
		);

		return self::main_response(
			array_merge(
				$input,
				[
					'hook'          => $hook_name,
					'accepted_args' => $accepted_args,
					'registered'    => true,
					'event_time'    => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_do_action( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::error_response( 'Hook name is required', $input );
		}

		$arg_1 = $config['arg_1'] ?? null;
		$arg_2 = $config['arg_2'] ?? null;
		do_action( $hook_name, $arg_1, $arg_2 );

		return self::main_response(
			array_merge(
				$input,
				[
					'hook'       => $hook_name,
					'arg_1'      => $arg_1,
					'arg_2'      => $arg_2,
					'triggered'  => true,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	public static function vendors_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$options = [
			[
				'name'  => 'any',
				'label' => 'Any Vendor',
			],
		];

		if ( ! function_exists( 'dokan_get_sellers' ) ) {
			return $options;
		}

		$limit  = max( 1, (int) ( $q['limit'] ?? 50 ) );
		$search = trim( (string) ( $q['search'] ?? '' ) );
		$result = dokan_get_sellers(
			[
				'number' => $limit,
				'paged'  => 1,
				'search' => $search,
			]
		);

		$users = is_array( $result ) ? ( $result['users'] ?? [] ) : [];
		foreach ( $users as $user ) {
			$vendor_id = self::parse_positive_int( $user );
			if ( $vendor_id <= 0 ) {
				continue;
			}

			$vendor = self::build_vendor_payload_from_id( $vendor_id );
			$label  = (string) ( $vendor['store_name'] ?? '' );
			if ( '' === $label ) {
				$label = 'Vendor #' . $vendor_id;
			}

			$options[] = [
				'name'  => (string) $vendor_id,
				'label' => $label . ' (#' . $vendor_id . ')',
			];
		}

		return $options;
	}

	public static function products_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$options = [
			[
				'name'  => 'any',
				'label' => 'Any Product',
			],
		];

		if ( ! function_exists( 'get_posts' ) ) {
			return $options;
		}

		$vendor_id = self::parse_positive_int( $q['vendor_id'] ?? 0 );
		$limit     = max( 1, (int) ( $q['limit'] ?? 50 ) );
		$search    = trim( (string) ( $q['search'] ?? '' ) );

		$args = [
			'post_type'      => 'product',
			'post_status'    => [ 'publish', 'pending', 'draft' ],
			'posts_per_page' => $limit,
			'fields'         => 'ids',
		];

		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( $vendor_id > 0 ) {
			$args['author'] = $vendor_id;
		}

		$products = get_posts( $args );
		foreach ( $products as $product_id ) {
			$product_id = self::parse_positive_int( $product_id );
			if ( $product_id <= 0 ) {
				continue;
			}

			$post  = get_post( $product_id );
			$title = $post ? (string) ( $post->post_title ?? '' ) : '';
			if ( '' === $title ) {
				$title = 'Product #' . $product_id;
			}

			$options[] = [
				'name'  => (string) $product_id,
				'label' => $title . ' (#' . $product_id . ')',
			];
		}

		return $options;
	}

	public static function withdraws_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$options = [
			[
				'name'  => 'any',
				'label' => 'Any Withdraw',
			],
		];

		$args = [
			'limit'  => max( 1, (int) ( $q['limit'] ?? 50 ) ),
			'offset' => 0,
		];

		$vendor_id = self::parse_positive_int( $q['vendor_id'] ?? 0 );
		if ( $vendor_id > 0 ) {
			$args['user_id'] = $vendor_id;
		}

		foreach ( self::resolve_withdraw_collection( $args ) as $withdraw ) {
			$payload = self::build_withdraw_payload( $withdraw );
			if ( empty( $payload ) ) {
				continue;
			}

			$withdraw_id = (int) ( $payload['withdraw_id'] ?? 0 );
			if ( $withdraw_id <= 0 ) {
				continue;
			}

			$options[] = [
				'name'  => (string) $withdraw_id,
				'label' => '#' . $withdraw_id . ' - ' . ucfirst( (string) ( $payload['status'] ?? 'pending' ) ),
			];
		}

		return $options;
	}

	private static function build_vendor_payload_from_id( int $vendor_id ): array {
		if ( $vendor_id <= 0 ) {
			return [];
		}

		$store_info = self::resolve_store_info( $vendor_id );
		$user       = function_exists( 'get_userdata' ) ? get_userdata( $vendor_id ) : null;
		$email      = $user ? (string) ( $user->user_email ?? '' ) : '';

		return [
			'vendor_id'     => $vendor_id,
			'user_id'       => $vendor_id,
			'store_name'    => self::resolve_store_name( $store_info, $vendor_id ),
			'store_url'     => self::resolve_store_url( $vendor_id, $store_info ),
			'display_name'  => $user ? (string) ( $user->display_name ?? '' ) : '',
			'user_login'    => $user ? (string) ( $user->user_login ?? '' ) : '',
			'user_email'    => $email,
			'store_phone'   => (string) ( $store_info['phone'] ?? '' ),
			'is_enabled'    => self::resolve_vendor_enabled( $store_info, $vendor_id ),
			'store_info'    => $store_info,
		];
	}

	private static function resolve_store_info( int $vendor_id ): array {
		if ( $vendor_id <= 0 || ! function_exists( 'dokan_get_store_info' ) ) {
			return [];
		}

		$store_info = dokan_get_store_info( $vendor_id );
		return is_array( $store_info ) ? $store_info : [];
	}

	private static function resolve_store_name( array $store_info, int $vendor_id ): string {
		$name = (string) ( $store_info['store_name'] ?? '' );
		if ( '' !== $name ) {
			return $name;
		}

		$user = function_exists( 'get_userdata' ) ? get_userdata( $vendor_id ) : null;
		if ( $user ) {
			$name = (string) ( $user->display_name ?? '' );
		}

		return '' !== $name ? $name : 'Vendor #' . $vendor_id;
	}

	private static function resolve_store_url( int $vendor_id, array $store_info ): string {
		if ( function_exists( 'dokan_get_store_url' ) ) {
			return (string) dokan_get_store_url( $vendor_id );
		}

		$url = (string) ( $store_info['store_url'] ?? '' );
		return $url;
	}

	private static function resolve_vendor_enabled( array $store_info, int $vendor_id ): bool {
		$enabled = $store_info['dokan_enable_selling'] ?? null;
		if ( is_string( $enabled ) ) {
			return in_array( strtolower( $enabled ), [ 'yes', '1', 'true', 'on' ], true );
		}

		if ( is_bool( $enabled ) ) {
			return $enabled;
		}

		if ( function_exists( 'dokan_is_seller_enabled' ) ) {
			return (bool) dokan_is_seller_enabled( $vendor_id );
		}

		return true;
	}

	private static function build_product_payload( int $product_id, array $extra = [] ): array {
		$post = function_exists( 'get_post' ) ? get_post( $product_id ) : null;
		$author_id = self::parse_positive_int( $post->post_author ?? 0 );
		$vendor_id = self::resolve_product_vendor_id( $product_id, $author_id );

		return array_merge(
			[
				'product_id'    => $product_id,
				'vendor_id'     => $vendor_id,
				'vendor'        => $vendor_id > 0 ? self::build_vendor_payload_from_id( $vendor_id ) : [],
				'post_author'   => $author_id,
				'post_title'    => $post ? (string) ( $post->post_title ?? '' ) : '',
				'post_status'   => $post ? (string) ( $post->post_status ?? '' ) : '',
				'post_type'     => $post ? (string) ( $post->post_type ?? '' ) : '',
				'post_date'     => $post ? (string) ( $post->post_date ?? '' ) : '',
				'post_modified' => $post ? (string) ( $post->post_modified ?? '' ) : '',
			],
			$extra
		);
	}

	private static function resolve_product_vendor_id( int $product_id, int $author_id ): int {
		if ( $author_id > 0 ) {
			return $author_id;
		}

		if ( function_exists( 'dokan_get_vendor_by_product' ) ) {
			$vendor_id = dokan_get_vendor_by_product( $product_id, true );
			return self::parse_positive_int( $vendor_id );
		}

		return 0;
	}

	private static function build_order_payload( int $order_id, int $vendor_id ): array {
		if ( $vendor_id <= 0 && function_exists( 'dokan_get_seller_id_by_order' ) ) {
			$vendor_id = self::parse_positive_int( dokan_get_seller_id_by_order( $order_id ) );
		}

		$payload = [
			'order_id'  => $order_id,
			'vendor_id' => $vendor_id,
			'vendor'    => $vendor_id > 0 ? self::build_vendor_payload_from_id( $vendor_id ) : [],
		];

		if ( ! function_exists( 'wc_get_order' ) ) {
			return $payload;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! is_object( $order ) ) {
			return $payload;
		}

		$payload['status']      = method_exists( $order, 'get_status' ) ? (string) $order->get_status() : '';
		$payload['total']       = method_exists( $order, 'get_total' ) ? (float) $order->get_total() : 0.0;
		$payload['currency']    = method_exists( $order, 'get_currency' ) ? (string) $order->get_currency() : '';
		$payload['customer_id'] = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;

		return $payload;
	}

	private static function resolve_withdraw_entity( $value ) {
		if ( is_object( $value ) && method_exists( $value, 'get_id' ) ) {
			return $value;
		}

		$withdraw_id = self::parse_positive_int( $value );
		if ( $withdraw_id <= 0 || ! function_exists( 'dokan' ) ) {
			return null;
		}

		$app = dokan();
		if ( ! is_object( $app ) || ! isset( $app->withdraw ) || ! is_object( $app->withdraw ) ) {
			return null;
		}

		if ( method_exists( $app->withdraw, 'get' ) ) {
			return $app->withdraw->get( $withdraw_id );
		}

		if ( method_exists( $app->withdraw, 'find' ) ) {
			return $app->withdraw->find( $withdraw_id );
		}

		if ( method_exists( $app->withdraw, 'get_withdraw' ) ) {
			return $app->withdraw->get_withdraw( $withdraw_id );
		}

		return null;
	}

	private static function build_withdraw_payload( $withdraw, array $extra = [] ): array {
		if ( ! is_object( $withdraw ) && ! is_array( $withdraw ) ) {
			return [];
		}

		$raw_id = is_object( $withdraw ) && method_exists( $withdraw, 'get_id' )
			? $withdraw->get_id()
			: ( $withdraw['id'] ?? 0 );
		$withdraw_id = self::parse_positive_int( $raw_id );
		if ( $withdraw_id <= 0 ) {
			return [];
		}

		$raw_vendor_id = is_object( $withdraw ) && method_exists( $withdraw, 'get_user_id' )
			? $withdraw->get_user_id()
			: ( $withdraw['user_id'] ?? 0 );
		$vendor_id = self::parse_positive_int( $raw_vendor_id );

		$raw_status = is_object( $withdraw ) && method_exists( $withdraw, 'get_status' )
			? $withdraw->get_status()
			: ( $withdraw['status'] ?? '' );
		$status = self::normalize_withdraw_status( $raw_status );

		$status_code = is_numeric( $raw_status )
			? (int) $raw_status
			: self::resolve_withdraw_status_code( $status );

		$amount = is_object( $withdraw ) && method_exists( $withdraw, 'get_amount' )
			? (float) $withdraw->get_amount()
			: (float) ( $withdraw['amount'] ?? 0 );
		$method = is_object( $withdraw ) && method_exists( $withdraw, 'get_method' )
			? (string) $withdraw->get_method()
			: (string) ( $withdraw['method'] ?? '' );
		$date = is_object( $withdraw ) && method_exists( $withdraw, 'get_date' )
			? (string) $withdraw->get_date()
			: (string) ( $withdraw['date'] ?? '' );
		$note = is_object( $withdraw ) && method_exists( $withdraw, 'get_note' )
			? (string) $withdraw->get_note()
			: (string) ( $withdraw['note'] ?? '' );
		$details = is_object( $withdraw ) && method_exists( $withdraw, 'get_details' )
			? $withdraw->get_details()
			: ( $withdraw['details'] ?? [] );

		return array_merge(
			[
				'withdraw_id'  => $withdraw_id,
				'vendor_id'    => $vendor_id,
				'vendor'       => $vendor_id > 0 ? self::build_vendor_payload_from_id( $vendor_id ) : [],
				'status'       => $status,
				'status_code'  => $status_code,
				'amount'       => $amount,
				'method'       => $method,
				'date'         => $date,
				'note'         => $note,
				'details'      => is_array( $details ) ? $details : [ 'value' => $details ],
			],
			$extra
		);
	}

	private static function normalize_withdraw_status( $status ): string {
		if ( is_numeric( $status ) ) {
			return self::resolve_withdraw_status_name_by_code( (int) $status );
		}

		$status = sanitize_key( (string) $status );
		if ( in_array( $status, [ 'pending', 'approved', 'cancelled', 'any' ], true ) ) {
			return $status;
		}

		return '';
	}

	private static function resolve_withdraw_status_code( string $status ): int {
		switch ( $status ) {
			case 'pending':
				return 0;
			case 'approved':
				return 1;
			case 'cancelled':
				return 2;
		}

		return 0;
	}

	private static function resolve_withdraw_status_name_by_code( int $code ): string {
		if ( function_exists( 'dokan' ) ) {
			$app = dokan();
			if ( is_object( $app ) && isset( $app->withdraw ) && is_object( $app->withdraw ) && method_exists( $app->withdraw, 'get_status_name' ) ) {
				$name = (string) $app->withdraw->get_status_name( $code );
				if ( '' !== $name ) {
					return sanitize_key( $name );
				}
			}
		}

		switch ( $code ) {
			case 0:
				return 'pending';
			case 1:
				return 'approved';
			case 2:
				return 'cancelled';
		}

		return '';
	}

	private static function resolve_withdraw_collection( array $args ): array {
		if ( ! function_exists( 'dokan' ) ) {
			return [];
		}

		$app = dokan();
		if ( ! is_object( $app ) || ! isset( $app->withdraw ) || ! is_object( $app->withdraw ) ) {
			return [];
		}

		$result = null;
		if ( method_exists( $app->withdraw, 'all' ) ) {
			$result = $app->withdraw->all( $args );
		} elseif ( method_exists( $app->withdraw, 'get_withdraw_requests' ) ) {
			$result = $app->withdraw->get_withdraw_requests(
				$args['user_id'] ?? '',
				$args['status'] ?? 0,
				$args['limit'] ?? 20,
				$args['offset'] ?? 0
			);
		}

		if ( is_object( $result ) && isset( $result->withdraws ) && is_array( $result->withdraws ) ) {
			return $result->withdraws;
		}

		if ( is_object( $result ) && isset( $result->items ) && is_array( $result->items ) ) {
			return $result->items;
		}

		return is_array( $result ) ? $result : [];
	}

	private static function matches_id_filter( array $config, string $config_key, int $actual_id ): bool {
		if ( ! array_key_exists( $config_key, $config ) ) {
			return true;
		}

		$selected = $config[ $config_key ];
		if ( self::is_any_selection( $selected ) ) {
			return true;
		}

		$selected_id = self::parse_positive_int( $selected );
		if ( $selected_id <= 0 ) {
			return true;
		}

		if ( $actual_id <= 0 ) {
			return false;
		}

		return $selected_id === $actual_id;
	}

	private static function is_any_selection( $value ): bool {
		if ( null === $value || false === $value ) {
			return true;
		}

		if ( is_string( $value ) ) {
			$normalized = sanitize_key( trim( $value ) );
			return '' === $normalized || 'any' === $normalized;
		}

		if ( is_array( $value ) ) {
			foreach ( [ 'value', 'name', 'id' ] as $key ) {
				if ( array_key_exists( $key, $value ) && self::is_any_selection( $value[ $key ] ) ) {
					return true;
				}
			}

			return false;
		}

		if ( is_object( $value ) ) {
			foreach ( [ 'value', 'name', 'id' ] as $key ) {
				if ( isset( $value->{$key} ) && self::is_any_selection( $value->{$key} ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function parse_positive_int( $value ): int {
		if ( is_int( $value ) || is_float( $value ) ) {
			$parsed = (int) $value;
			return $parsed > 0 ? $parsed : 0;
		}

		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( '' === $value || 'any' === strtolower( $value ) ) {
				return 0;
			}

			if ( is_numeric( $value ) ) {
				$parsed = (int) $value;
				return $parsed > 0 ? $parsed : 0;
			}

			if ( preg_match( '/\d+/', $value, $matches ) ) {
				$parsed = isset( $matches[0] ) ? (int) $matches[0] : 0;
				return $parsed > 0 ? $parsed : 0;
			}

			return 0;
		}

		if ( is_array( $value ) ) {
			foreach ( [ 'id', 'ID', 'value', 'name', 'vendor_id', 'product_id', 'withdraw_id', 'user_id' ] as $key ) {
				if ( array_key_exists( $key, $value ) ) {
					return self::parse_positive_int( $value[ $key ] );
				}
			}

			if ( isset( $value[0] ) ) {
				return self::parse_positive_int( $value[0] );
			}

			return 0;
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'get_id' ) ) {
				return self::parse_positive_int( $value->get_id() );
			}

			foreach ( [ 'ID', 'id', 'value', 'name', 'vendor_id', 'product_id', 'withdraw_id', 'user_id' ] as $key ) {
				if ( isset( $value->{$key} ) ) {
					return self::parse_positive_int( $value->{$key} );
				}
			}

			return 0;
		}

		$parsed = (int) $value;
		return $parsed > 0 ? $parsed : 0;
	}

	private static function main_response( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	private static function error_response( string $message, array $input = [] ): array {
		return [
			'port' => 'error',
			'data' => array_merge(
				$input,
				[
					'error' => $message,
				]
			),
		];
	}
}
