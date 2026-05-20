<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use Zaplane\Models\Feedback;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FeedbackController extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'zaplane/v1';
		$this->rest_base = 'feedback';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_feedback' ],
					'permission_callback' => [ $this, 'admin_permission' ],
					'args'                => [
						'page'     => [ 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ],
						'per_page' => [ 'type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint' ],
						'rating'   => [ 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ],
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'submit' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'order_id' => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
						'key'      => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
						'rating'   => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
						'comment'  => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_summary' ],
				'permission_callback' => [ $this, 'admin_permission' ],
			]
		);
	}

	public function admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function list_feedback( \WP_REST_Request $request ): mixed {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$rating   = (int) $request->get_param( 'rating' );

		$base = Feedback::orderBy( 'id', 'desc' );
		if ( $rating > 0 ) {
			$base = $base->where( 'rating', $rating );
		}

		$total = $base->clone()->count();
		$items = $base->forPage( $page, $per_page )->get()->toArray();

		return rest_ensure_response( [
			'data'       => $items,
			'pagination' => [
				'page'        => $page,
				'per_page'    => $per_page,
				'total'       => $total,
				'total_pages' => (int) ceil( $total / $per_page ),
			],
		] );
	}

	public function get_summary(): mixed {
		$all = Feedback::orderBy( 'id', 'desc' )->get()->toArray();

		$total   = count( $all );
		$avg     = $total > 0 ? round( array_sum( array_column( $all, 'rating' ) ) / $total, 1 ) : 0;
		$by_star = array_fill( 1, 5, 0 );

		foreach ( $all as $row ) {
			$r = (int) $row['rating'];
			if ( isset( $by_star[ $r ] ) ) {
				$by_star[ $r ]++;
			}
		}

		return rest_ensure_response( [
			'total'   => $total,
			'average' => $avg,
			'by_star' => $by_star,
		] );
	}

	public function submit( \WP_REST_Request $request ): mixed {
		$order_id = (int) $request->get_param( 'order_id' );
		$key      = (string) $request->get_param( 'key' );
		$rating   = (int) $request->get_param( 'rating' );
		$comment  = (string) ( $request->get_param( 'comment' ) ?? '' );

		if ( $rating < 1 || $rating > 5 ) {
			return new WP_Error( 'invalid_rating', 'Rating must be between 1 and 5.', [ 'status' => 422 ] );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'order_not_found', 'Order not found.', [ 'status' => 404 ] );
		}

		if ( ! hash_equals( $order->get_order_key(), $key ) ) {
			return new WP_Error( 'invalid_key', 'Invalid order key.', [ 'status' => 403 ] );
		}

		if ( Feedback::for_order( $order_id ) ) {
			return new WP_Error( 'already_submitted', 'Feedback already submitted for this order.', [ 'status' => 409 ] );
		}

		Feedback::create( [
			'order_id' => $order_id,
			'rating'   => $rating,
			'comment'  => $comment,
			'email'    => $order->get_billing_email(),
		] );

		$stars = str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );
		$note  = sprintf( 'Customer feedback received: %s (%d/5)', $stars, $rating );
		if ( $comment ) {
			$note .= "\n" . $comment;
		}
		$order->add_order_note( $note );

		return rest_ensure_response( [ 'success' => true ] );
	}
}
