<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;
use Zaplane\Admin\Setup\UnitGenerator;
use Zaplane\Admin\Setup\WorkerStatus;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Maintenance\RunRetention;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST endpoints for the VPS Setup admin page (React).
 *
 * Exposes worker heartbeat, environment detection, persisted settings,
 * and on-demand unit-file generation under /zaplane/v1/worker/*.
 */
class WorkerController extends WP_REST_Controller {

	private const SETTINGS_OPTION = 'zaplane_worker_settings';
	private const ENABLED_OPTION  = 'zaplane_worker_enabled';

	protected ?Container $container = null;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
	}

	public function register_routes(): void {
		$ns = 'zaplane/v1';

		register_rest_route( $ns, '/worker/status', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_status' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $ns, '/worker/environment', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_environment' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $ns, '/worker/settings', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'memory'   => [ 'type' => 'integer', 'sanitize_callback' => 'absint' ],
					'max_jobs' => [ 'type' => 'integer', 'sanitize_callback' => 'absint' ],
					'max_time' => [ 'type' => 'integer', 'sanitize_callback' => 'absint' ],
					'runner'   => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
					'enabled'  => [ 'type' => 'boolean' ],
				],
			],
		] );

		register_rest_route( $ns, '/worker/unit-file', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_unit_file' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'type' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
		] );

		register_rest_route( $ns, '/worker/failures', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_failures' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'limit' => [ 'type' => 'integer', 'sanitize_callback' => 'absint' ],
				],
			],
		] );

		register_rest_route( $ns, '/worker/retention', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_retention' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_retention' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'days' => [ 'type' => 'integer', 'sanitize_callback' => 'absint' ],
				],
			],
		] );

		register_rest_route( $ns, '/worker/cleanup', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'run_cleanup' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_status() {
		return rest_ensure_response( [
			'status'         => WorkerStatus::get(),
			'jobs_per_min'   => WorkerStatus::jobs_per_minute(),
			'fetched_at'     => time(),
		] );
	}

	public function get_environment() {
		$env    = UnitGenerator::detect_environment();
		$runner = UnitGenerator::recommended_runner( $env );
		return rest_ensure_response( [
			'environment'        => $env,
			'recommended_runner' => $runner,
		] );
	}

	public function get_settings() {
		$settings = $this->get_persisted_settings();
		return rest_ensure_response( $settings );
	}

	public function save_settings( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( empty( $body ) ) {
			$body = $request->get_params();
		}

		$runner = isset( $body['runner'] ) ? (string) $body['runner'] : 'systemd';
		if ( ! in_array( $runner, [ 'systemd', 'supervisor' ], true ) ) {
			$runner = 'systemd';
		}

		$settings = [
			'memory'   => max( 64, (int) ( $body['memory'] ?? 256 ) ),
			'max_jobs' => max( 0, (int) ( $body['max_jobs'] ?? 1000 ) ),
			'max_time' => max( 0, (int) ( $body['max_time'] ?? 3600 ) ),
			'runner'   => $runner,
		];

		update_option( self::SETTINGS_OPTION, $settings, false );

		if ( array_key_exists( 'enabled', $body ) ) {
			update_option( self::ENABLED_OPTION, (bool) $body['enabled'] ? 1 : 0, false );
		}

		$settings['enabled'] = (bool) get_option( self::ENABLED_OPTION, 0 );

		return rest_ensure_response( $settings );
	}

	public function get_unit_file( WP_REST_Request $request ) {
		$type = $request->get_param( 'type' );
		if ( ! in_array( $type, [ 'systemd', 'supervisor' ], true ) ) {
			$settings = $this->get_persisted_settings();
			$type     = $settings['runner'] ?? 'systemd';
		}

		$env  = UnitGenerator::detect_environment();
		$opts = array_merge( $env, $this->get_persisted_settings() );

		if ( 'supervisor' === $type ) {
			return rest_ensure_response( [
				'type'          => 'supervisor',
				'path'          => '/etc/supervisor/conf.d/zaplane-worker.conf',
				'content'       => UnitGenerator::supervisor_conf( $env, $opts ),
				'install_steps' => UnitGenerator::supervisor_install_steps(),
			] );
		}

		return rest_ensure_response( [
			'type'          => 'systemd',
			'path'          => '/etc/systemd/system/zaplane-worker.service',
			'content'       => UnitGenerator::systemd_unit( $env, $opts ),
			'install_steps' => UnitGenerator::systemd_install_steps(),
		] );
	}

	public function get_failures( WP_REST_Request $request ) {
		$limit = (int) $request->get_param( 'limit' );
		$limit = $limit > 0 ? min( 100, $limit ) : 20;

		if ( ! class_exists( '\\ActionScheduler' ) ) {
			return rest_ensure_response( [ 'failures' => [], 'total' => 0 ] );
		}

		try {
			$store  = \ActionScheduler::store();
			$logger = \ActionScheduler::logger();

			$ids = $store->query_actions( [
				'group'    => 'zaplane',
				'status'   => \ActionScheduler_Store::STATUS_FAILED,
				'orderby'  => 'date',
				'order'    => 'DESC',
				'per_page' => $limit,
			] );

			$total = (int) $store->query_actions( [
				'group'  => 'zaplane',
				'status' => \ActionScheduler_Store::STATUS_FAILED,
			], 'count' );

			$failures = [];
			foreach ( $ids as $id ) {
				try {
					$action = $store->fetch_action( $id );
				} catch ( \Throwable $e ) {
					continue;
				}
				if ( ! $action ) {
					continue;
				}

				$schedule = $action->get_schedule();
				$scheduled_for = null;
				if ( $schedule && method_exists( $schedule, 'get_date' ) ) {
					$date = $schedule->get_date();
					if ( $date instanceof \DateTime ) {
						$scheduled_for = $date->format( 'c' );
					}
				}

				$message = '';
				$failed_at = null;
				try {
					$logs = $logger->get_logs( $id );
					foreach ( array_reverse( $logs ) as $log ) {
						$msg = $log->get_message();
						if ( false !== stripos( $msg, 'failed' ) || false !== stripos( $msg, 'exception' ) || false !== stripos( $msg, 'error' ) ) {
							$message   = $msg;
							$logdate   = $log->get_date();
							$failed_at = $logdate instanceof \DateTime ? $logdate->format( 'c' ) : null;
							break;
						}
					}
					if ( '' === $message && ! empty( $logs ) ) {
						$last      = end( $logs );
						$message   = $last->get_message();
						$logdate   = $last->get_date();
						$failed_at = $logdate instanceof \DateTime ? $logdate->format( 'c' ) : null;
					}
				} catch ( \Throwable $e ) {
					$message = '(could not read logs)';
				}

				$failures[] = [
					'id'            => (int) $id,
					'hook'          => $action->get_hook(),
					'args'          => $action->get_args(),
					'scheduled_for' => $scheduled_for,
					'failed_at'     => $failed_at,
					'message'       => $message,
				];
			}

			return rest_ensure_response( [
				'failures' => $failures,
				'total'    => $total,
				'limit'    => $limit,
			] );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'zaplane_failures_failed', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	public function get_retention() {
		return rest_ensure_response( [
			'days'     => RunRetention::get_retention_days(),
			'default'  => RunRetention::DEFAULT_DAYS,
			'last_run' => RunRetention::get_last_run(),
		] );
	}

	public function save_retention( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( empty( $body ) ) {
			$body = $request->get_params();
		}
		$days = isset( $body['days'] ) ? max( 0, (int) $body['days'] ) : RunRetention::DEFAULT_DAYS;
		RunRetention::set_retention_days( $days );
		return $this->get_retention();
	}

	public function run_cleanup() {
		$result = RunRetention::run_cleanup();
		return rest_ensure_response( $result );
	}

	private function get_persisted_settings(): array {
		$saved = get_option( self::SETTINGS_OPTION, [] );
		$base  = wp_parse_args( is_array( $saved ) ? $saved : [], [
			'memory'   => 256,
			'max_jobs' => 1000,
			'max_time' => 3600,
			'runner'   => 'systemd',
		] );
		$base['enabled'] = (bool) get_option( self::ENABLED_OPTION, 0 );
		return $base;
	}
}
