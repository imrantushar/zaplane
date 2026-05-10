<?php

namespace Zaplane\Modules\AbandonedCart\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use Zaplane\Modules\AbandonedCart\AbandonedCartModel;
use Zaplane\Framework\Database\ORM\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AbandonedCartController extends WP_REST_Controller {

	protected $namespace = 'zaplane/v1';
	protected $rest_base = 'abandoned-carts';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_carts' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_cart' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/bulk-delete', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bulk_delete' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/report', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_report' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_carts( $request ) {
		global $wpdb;

		$table    = Schema::getTable( 'abandonned_cart' );
		$status   = sanitize_text_field( $request->get_param( 'status' ) ?? '' );
		$search   = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
		$from     = sanitize_text_field( $request->get_param( 'date_from' ) ?? '' );
		$to       = sanitize_text_field( $request->get_param( 'date_to' ) ?? '' );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );

		$where  = '1=1';
		$params = [];

		if ( $status ) {
			$where   .= ' AND status = %s';
			$params[] = $status;
		}

		if ( $search ) {
			$where   .= ' AND (full_name LIKE %s OR email LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		if ( $from ) {
			$where   .= ' AND created_at >= %s';
			$params[] = $from . ' 00:00:00';
		}

		if ( $to ) {
			$where   .= ' AND created_at <= %s';
			$params[] = $to . ' 23:59:59';
		}

		$offset = ( $page - 1 ) * $per_page;

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", ...$params ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", ...array_merge( $params, [ $per_page, $offset ] ) ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
		}

		$data = array_map( function ( $row ) {
			$cart = is_string( $row['cart'] ) ? json_decode( $row['cart'], true ) : [];
			$row['cart'] = is_array( $cart ) ? $cart : [];
			return $row;
		}, $rows ?: [] );

		return rest_ensure_response( [
			'data'       => $data,
			'pagination' => [
				'page'        => $page,
				'per_page'    => $per_page,
				'total'       => (int) $total,
				'total_pages' => (int) ceil( $total / $per_page ),
			],
		] );
	}

	public function delete_cart( $request ) {
		$id   = (int) $request['id'];
		$cart = AbandonedCartModel::find( $id );

		if ( ! $cart ) {
			return new WP_Error( 'not_found', 'Cart not found', [ 'status' => 404 ] );
		}

		$cart->delete();

		return rest_ensure_response( [ 'deleted' => true, 'id' => $id ] );
	}

	public function bulk_delete( $request ) {
		$ids = array_map( 'absint', (array) ( $request->get_json_params()['ids'] ?? [] ) );

		if ( empty( $ids ) ) {
			return new WP_Error( 'invalid_ids', 'No IDs provided', [ 'status' => 400 ] );
		}

		AbandonedCartModel::whereIn( 'id', $ids )->delete();

		return rest_ensure_response( [ 'deleted' => true, 'count' => count( $ids ) ] );
	}

	public function get_report( $request ) {
		global $wpdb;

		$table    = Schema::getTable( 'abandonned_cart' );
		$from     = sanitize_text_field( $request->get_param( 'date_from' ) ?? '' );
		$to       = sanitize_text_field( $request->get_param( 'date_to' ) ?? '' );
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
		$symbol   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';

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

		foreach ( $statuses as $status ) {
			$status_where = $where . ' AND status = %s';
			$status_params = array_merge( $params, [ $status ] );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) as cnt, COALESCE(SUM(total), 0) as revenue FROM {$table} WHERE {$status_where}", ...$status_params ), ARRAY_A );

			$amount    = (float) ( $row['revenue'] ?? 0 );
			$count     = (int) ( $row['cnt'] ?? 0 );
			$formatted = function_exists( 'wc_price' ) ? strip_tags( wc_price( $amount, [ 'currency' => $currency ] ) ) : number_format( $amount, 2 );

			$summary[ $status . '_revenue' ] = [
				'orders'    => $count,
				'amount'    => $amount,
				'formatted' => $formatted,
			];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_row = ! empty( $params ) ? $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) as cnt, COALESCE(SUM(total), 0) as revenue FROM {$table} WHERE {$where}", ...$params ), ARRAY_A )
			: $wpdb->get_row( "SELECT COUNT(*) as cnt, COALESCE(SUM(total), 0) as revenue FROM {$table} WHERE {$where}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$total_carts     = (int) ( $total_row['cnt'] ?? 0 );
		$total_revenue   = (float) ( $total_row['revenue'] ?? 0 );
		$total_recovered = $summary['recovered_revenue']['orders'] ?? 0;

		$recovery_rate = $total_carts > 0 ? round( ( $total_recovered / $total_carts ) * 100, 2 ) : 0;

		$summary['recovery_rate'] = [
			'percentage' => $recovery_rate,
			'formatted'  => $recovery_rate . '%',
		];

		return rest_ensure_response( [
			'report_name' => 'Abandoned Carts - Reports',
			'currency'    => $currency,
			'date_range'  => [
				'from' => $from,
				'to'   => $to,
			],
			'summary'     => $summary,
			'totals'      => [
				'total_abandoned_carts'  => $total_carts,
				'total_recovered_carts'  => $total_recovered,
				'total_revenue'          => $total_revenue,
				'currency_symbol'        => $symbol,
			],
		] );
	}
}
