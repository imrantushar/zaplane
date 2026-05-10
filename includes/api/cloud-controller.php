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

		// Public unauthenticated probe used by the cloud at pairing time
		// to confirm Cloud → site connectivity (firewalls, IP allowlists,
		// etc.). Returns the plugin version so cloud can render a banner
		// when versions drift.
		register_rest_route( $ns, '/_ping', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'ping' ],
				'permission_callback' => '__return_true',
			],
		] );

		// Cloud-managed wp-cron receiver. Cloud schedules the job, fires
		// it back here, plugin runs do_action with the original args.
		// HMAC-signed identically to /cloud/execute.
		register_rest_route( $ns, '/cron/run', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'run_cron' ],
				'permission_callback' => [ $this, 'verify_cloud_signature' ],
				'args'                => [
					'hook' => [ 'type' => 'string', 'required' => true ],
					'args' => [ 'type' => 'array' ],
				],
			],
		] );
	}

	/**
	 * Public probe — anyone can hit this. Useful for uptime checks and
	 * the cloud's pairing pre-flight.
	 */
	public function ping() {
		return rest_ensure_response( [
			'ok'             => true,
			'plugin_version' => defined( 'ZAPLANE_VERSION' ) ? ZAPLANE_VERSION : null,
			'paired'         => Bridge::is_paired(),
			'time'           => time(),
		] );
	}

	/**
	 * Mirrors ExecuteController::verify_signature — HMAC-SHA256 over
	 * `timestamp + "\n" + raw_body` with the site_secret. Same scheme
	 * keeps the plugin's HMAC code path single.
	 */
	public function verify_cloud_signature( WP_REST_Request $request ): bool {
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

	/**
	 * Run a wp-cron hook on demand. The cloud is the source of truth
	 * for next_run_at; we just fire `do_action_ref_array()` with the
	 * stored args and report duration back so the cloud's history
	 * dashboard has timing data.
	 */
	public function run_cron( WP_REST_Request $request ) {
		$hook = (string) $request->get_param( 'hook' );
		$args = (array) ( $request->get_param( 'args' ) ?? [] );

		if ( $hook === '' ) {
			return new \WP_Error( 'zaplane_cron_missing_hook', 'hook is required', [ 'status' => 400 ] );
		}

		$started_at = microtime( true );
		try {
			do_action_ref_array( $hook, $args );
			$duration_ms = (int) ( ( microtime( true ) - $started_at ) * 1000 );
			return rest_ensure_response( [
				'ok'           => true,
				'hook'         => $hook,
				'duration_ms'  => $duration_ms,
			] );
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'zaplane_cron_run_failed',
				$e->getMessage(),
				[ 'status' => 500, 'duration_ms' => (int) ( ( microtime( true ) - $started_at ) * 1000 ) ]
			);
		}
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_status() {
		$summary = Bridge::public_summary();
		// Surface the degraded-mode tracker so the admin banner can warn
		// the user when we've fallen back to local execution.
		if ( class_exists( '\\Zaplane\\Framework\\Cloud\\DegradedMode' ) ) {
			$summary['degraded'] = \Zaplane\Framework\Cloud\DegradedMode::status();
		}
		return rest_ensure_response( $summary );
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
