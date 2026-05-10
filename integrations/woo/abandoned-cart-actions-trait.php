<?php
namespace Zaplane\Integrations\Woo;

use Zaplane\Modules\AbandonedCart\AbandonedCartModel;
use Zaplane\Framework\Database\ORM\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait AbandonedCartActionsTrait {

	private static function abandoned_cart_payload( AbandonedCartModel $cart ): array {
		return [
			'id'           => $cart->id,
			'full_name'    => $cart->full_name,
			'email'        => $cart->email,
			'status'       => $cart->status,
			'total'        => $cart->total,
			'subtotal'     => $cart->subtotal,
			'shipping'     => $cart->shipping,
			'tax'          => $cart->tax,
			'discounts'    => $cart->discounts,
			'fees'         => $cart->fees,
			'currency'     => $cart->currency,
			'checkout_key' => $cart->checkout_key,
			'cart_hash'    => $cart->cart_hash,
			'is_optout'    => $cart->is_optout,
			'user_id'      => $cart->user_id,
			'contact_id'   => $cart->contact_id,
			'order_id'     => $cart->order_id,
			'click_counts' => $cart->click_counts,
			'note'         => $cart->note,
			'provider'     => $cart->provider,
			'cart'         => $cart->get_cart_contents(),
			'abandoned_at' => $cart->abandoned_at,
			'recovered_at' => $cart->recovered_at,
			'created_at'   => $cart->created_at,
			'updated_at'   => $cart->updated_at,
		];
	}

	private static function abandoned_cart_trigger_payload( array $args ): ?array {
		$cart = $args[0] ?? null;
		if ( ! $cart ) {
			return null;
		}
		if ( $cart instanceof AbandonedCartModel ) {
			return self::abandoned_cart_payload( $cart );
		}
		if ( is_array( $cart ) ) {
			return $cart;
		}
		return null;
	}

	private static function action_get_abandoned_cart( array $config, array $input ): array {
		$cart_id = (int) ( $config['cart_id'] ?? 0 );
		if ( ! $cart_id ) {
			return self::error( 'cart_id is required' );
		}
		$cart = AbandonedCartModel::find( $cart_id );
		if ( ! $cart ) {
			return self::error( 'Abandoned cart not found' );
		}
		return self::respond( self::abandoned_cart_payload( $cart ) );
	}

	private static function action_get_abandoned_cart_by_email( array $config, array $input ): array {
		$email = sanitize_email( $config['email'] ?? '' );
		if ( ! $email ) {
			return self::error( 'email is required' );
		}
		$cart = AbandonedCartModel::where( 'email', $email )
			->orderBy( 'id', 'desc' )
			->first();
		if ( ! $cart ) {
			return self::error( 'No abandoned cart found for that email' );
		}
		return self::respond( self::abandoned_cart_payload( $cart ) );
	}

	private static function action_get_abandoned_carts( array $config, array $input ): array {
		$status = sanitize_text_field( $config['status'] ?? '' );
		$limit  = max( 1, min( 100, (int) ( $config['limit'] ?? 20 ) ) );

		$query = AbandonedCartModel::orderBy( 'id', 'desc' );
		if ( $status ) {
			$query = $query->where( 'status', $status );
		}

		$carts = $query->limit( $limit )->get();
		$items = [];
		if ( $carts ) {
			foreach ( $carts as $cart ) {
				$items[] = self::abandoned_cart_payload( $cart );
			}
		}

		return self::respond( [ 'carts' => $items, 'count' => count( $items ) ] );
	}

	private static function action_update_abandoned_cart_status( array $config, array $input ): array {
		$cart_id = (int) ( $config['cart_id'] ?? 0 );
		$status  = sanitize_text_field( $config['status'] ?? '' );

		if ( ! $cart_id || ! $status ) {
			return self::error( 'cart_id and status are required' );
		}

		$allowed = [ 'draft', 'processing', 'recovered', 'lost', 'opt_out', 'skipped' ];
		if ( ! in_array( $status, $allowed, true ) ) {
			return self::error( 'Invalid status value' );
		}

		$update = [ 'status' => $status, 'updated_at' => current_time( 'mysql' ) ];
		if ( 'recovered' === $status ) {
			$update['recovered_at'] = current_time( 'mysql' );
		}

		AbandonedCartModel::where( 'id', $cart_id )->update( $update );

		return self::respond( [ 'cart_id' => $cart_id, 'status' => $status, 'updated' => true ] );
	}

	private static function action_get_abandoned_cart_report( array $config, array $input ): array {
		global $wpdb;

		$table = Schema::getTable( 'abandonned_cart' );
		$from  = sanitize_text_field( $config['date_from'] ?? '' );
		$to    = sanitize_text_field( $config['date_to'] ?? '' );

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
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as revenue FROM {$table} WHERE {$st_where}", ...$st_params ), ARRAY_A );
			$summary[ $st ] = [
				'count'   => (int) ( $row['cnt'] ?? 0 ),
				'revenue' => (float) ( $row['revenue'] ?? 0 ),
			];
		}

		$total_all     = array_sum( array_column( $summary, 'count' ) );
		$recovery_rate = $total_all > 0 ? round( ( $summary['recovered']['count'] / $total_all ) * 100, 2 ) : 0;

		return self::respond( [
			'summary'       => $summary,
			'recovery_rate' => $recovery_rate,
			'total_carts'   => $total_all,
			'date_range'    => [ 'from' => $from, 'to' => $to ],
		] );
	}
}
