<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Cloud\Bridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloud → plugin action callback.
 *
 * When the cloud workflow runner hits a step that needs to run inside
 * WordPress (e.g. enroll a LearnDash student, create a WC coupon), it
 * POSTs to this endpoint with a signed payload. We verify the HMAC,
 * resolve the integration, run execute_node(), return the output.
 *
 * Request body:
 *   {
 *     "integration": "learndash",
 *     "action":      "enroll_user",
 *     "config":      {...},          // node config (resolved on cloud)
 *     "input":       {...},          // input data from previous step
 *     "idempotency_key": "..."       // skip if seen before
 *   }
 *
 * Response: { ok: bool, output?: array, error?: string }
 */
class ExecuteController extends WP_REST_Controller {

	private const IDEMPOTENCY_OPTION = 'zaplane_execute_idempotency';
	private const IDEMPOTENCY_TTL    = 86400; // 24h

	protected ?Container $container = null;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
	}

	public function register_routes(): void {
		register_rest_route( 'zaplane/v1', '/cloud/execute', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'execute' ],
				'permission_callback' => [ $this, 'verify_signature' ],
				'args'                => [
					'integration' => [ 'type' => 'string', 'required' => true ],
					'action'      => [ 'type' => 'string', 'required' => true ],
					'config'      => [ 'type' => 'object' ],
					'input'       => [ 'type' => 'object' ],
					'idempotency_key' => [ 'type' => 'string' ],
				],
			],
		] );
	}

	/**
	 * Mirrors the cloud-side VerifySiteHmac middleware: HMAC-SHA256 over
	 * `timestamp + "\n" + raw_body` signed with our site_secret.
	 */
	public function verify_signature( WP_REST_Request $request ): bool {
		if ( ! Bridge::is_paired() ) {
			return false;
		}
		$state = Bridge::get_state();

		$site_id   = $request->get_header( 'x_zaplane_site' );
		$timestamp = $request->get_header( 'x_zaplane_timestamp' );
		$signature = $request->get_header( 'x_zaplane_signature' );

		if ( ! $site_id || ! $timestamp || ! $signature ) {
			return false;
		}
		if ( (int) $site_id !== (int) $state['site_id'] ) {
			return false;
		}
		if ( abs( time() - (int) $timestamp ) > 600 ) {
			return false;
		}

		$body     = $request->get_body();
		$expected = hash_hmac( 'sha256', $timestamp . "\n" . $body, $state['site_secret'] );

		return hash_equals( $expected, (string) $signature );
	}

	public function execute( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( empty( $body ) ) {
			$body = $request->get_params();
		}

		$integration_slug = (string) ( $body['integration'] ?? '' );
		$action_slug      = (string) ( $body['action'] ?? '' );
		$idempotency_key  = (string) ( $body['idempotency_key'] ?? '' );

		// Idempotency: skip if we already processed this key.
		if ( '' !== $idempotency_key ) {
			$cached = $this->get_idempotency_record( $idempotency_key );
			if ( null !== $cached ) {
				return rest_ensure_response( array_merge( $cached, [ 'cached' => true ] ) );
			}
		}

		try {
			$loader = $this->container ? $this->container->get( 'integrations' ) : null;
			if ( ! $loader ) {
				return new \WP_Error( 'zaplane_no_loader', 'IntegrationLoader not available.', [ 'status' => 500 ] );
			}

			$integration = $loader->get( $integration_slug );
			if ( ! $integration ) {
				return new \WP_Error( 'zaplane_unknown_integration', 'Integration not registered: ' . $integration_slug, [ 'status' => 400 ] );
			}

			// Build a node-shaped payload that integration::execute_node expects.
			// The cloud sends config + input; plugin integrations receive them
			// via a node array with `data` and `_connection_credentials`.
			$node = [
				'id'   => 'cloud-' . substr( md5( $idempotency_key ?: uniqid( '', true ) ), 0, 8 ),
				'type' => 'action',
				'data' => array_merge(
					(array) ( $body['config'] ?? [] ),
					[
						'app'    => $integration_slug,
						'action' => $action_slug,
					]
				),
			];

			$input = (array) ( $body['input'] ?? [] );

			$class_name = get_class( $integration );
			$output = $class_name::execute_node( $node, $input );

			$result = [
				'ok'     => true,
				'output' => is_array( $output ) ? $output : [ 'result' => $output ],
			];

			if ( '' !== $idempotency_key ) {
				$this->store_idempotency_record( $idempotency_key, $result );
			}

			return rest_ensure_response( $result );
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'zaplane_execute_failed',
				$e->getMessage(),
				[ 'status' => 500, 'trace' => defined( 'WP_DEBUG' ) && WP_DEBUG ? $e->getTraceAsString() : null ]
			);
		}
	}

	private function get_idempotency_record( string $key ): ?array {
		$store = get_option( self::IDEMPOTENCY_OPTION, [] );
		if ( ! is_array( $store ) || ! isset( $store[ $key ] ) ) {
			return null;
		}
		$entry = $store[ $key ];
		if ( ( $entry['stored_at'] ?? 0 ) < time() - self::IDEMPOTENCY_TTL ) {
			return null;
		}
		return $entry['result'] ?? null;
	}

	private function store_idempotency_record( string $key, array $result ): void {
		$store = get_option( self::IDEMPOTENCY_OPTION, [] );
		if ( ! is_array( $store ) ) {
			$store = [];
		}

		// Drop expired entries opportunistically so the option doesn't grow forever.
		$now = time();
		foreach ( $store as $k => $v ) {
			if ( ( $v['stored_at'] ?? 0 ) < $now - self::IDEMPOTENCY_TTL ) {
				unset( $store[ $k ] );
			}
		}

		$store[ $key ] = [
			'result'    => $result,
			'stored_at' => $now,
		];

		// Cap at 500 most-recent entries to bound option size.
		if ( count( $store ) > 500 ) {
			uasort( $store, fn( $a, $b ) => ( $b['stored_at'] ?? 0 ) <=> ( $a['stored_at'] ?? 0 ) );
			$store = array_slice( $store, 0, 500, true );
		}

		update_option( self::IDEMPOTENCY_OPTION, $store, false );
	}
}
