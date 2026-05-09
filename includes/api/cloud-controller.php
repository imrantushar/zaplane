<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Cloud\Bridge;
use Zaplane\Framework\Cloud\Pairing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST endpoints driving the Settings → Cloud Connection UI.
 */
class CloudController extends WP_REST_Controller {

	protected ?Container $container = null;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
	}

	public function register_routes(): void {
		$ns = 'zaplane/v1';

		register_rest_route( $ns, '/cloud/status', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_status' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $ns, '/cloud/pair', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'pair' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'cloud_url' => [ 'type' => 'string', 'required' => true ],
					'code'      => [ 'type' => 'string', 'required' => true ],
				],
			],
		] );

		register_rest_route( $ns, '/cloud/disconnect', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'disconnect' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $ns, '/cloud/test', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'test_connection' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_status() {
		return rest_ensure_response( Bridge::public_summary() );
	}

	public function pair( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( empty( $body ) ) {
			$body = $request->get_params();
		}

		$cloud_url = (string) ( $body['cloud_url'] ?? '' );
		$code      = (string) ( $body['code'] ?? '' );

		$result = Pairing::pair( $cloud_url, $code );

		if ( ! $result['ok'] ) {
			return new \WP_Error( 'zaplane_pairing_failed', $result['error'], [ 'status' => 400 ] );
		}

		return rest_ensure_response( $result['summary'] );
	}

	public function disconnect() {
		Pairing::disconnect();
		return rest_ensure_response( Bridge::public_summary() );
	}

	public function test_connection() {
		$result = Pairing::heartbeat();
		if ( ! $result['ok'] ) {
			return new \WP_Error(
				'zaplane_cloud_test_failed',
				$result['error'] ?: 'Heartbeat failed',
				[ 'status' => 400, 'body' => $result['body'] ?? [] ]
			);
		}
		return rest_ensure_response( [ 'ok' => true, 'response' => $result['body'] ] );
	}
}
