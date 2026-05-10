<?php

namespace Zaplane\Framework\Cloud;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ships resolved workflow trigger events from a paired plugin to the
 * Zaplane Cloud ingest endpoint.
 *
 * Subscribes to `zaplane/trigger_event_resolved` (fired inside
 * Automation::trigger_router after a successful resolve_trigger). The
 * actual HTTP POST is handed to Action Scheduler so the page that fired
 * the original WordPress hook (e.g. a checkout) returns immediately.
 *
 * For now we *also* execute locally so existing automations don't break
 * before the cloud-side workflow runner exists. Once that lands, this
 * class can switch to "cloud-primary" by short-circuiting local exec.
 */
class EventForwarder {

	public const HOOK_FORWARD = 'zaplane_cloud_forward_event';
	public const GROUP        = 'zaplane';

	public static function bootstrap(): void {
		add_action( 'zaplane/trigger_event_resolved', [ self::class, 'on_event_resolved' ], 10, 3 );
		add_action( self::HOOK_FORWARD, [ self::class, 'process_forward' ], 10, 1 );

		// Cloud-primary execution: when paired, don't run workflows locally —
		// the cloud is the source of truth. The plugin still records the
		// run row in its local DB at start_trigger_run time? No — by
		// returning true here we skip start_trigger_run entirely, so the
		// local `wp_zaplane_runs` table stays empty for cloud-handled
		// events. Customer's history lives in the cloud's runs page.
		add_filter( 'zaplane/skip_local_run', [ self::class, 'should_skip_local' ], 10, 1 );
	}

	public static function should_skip_local( bool $skip ): bool {
		// If something else already decided to skip, respect that. Otherwise
		// skip when paired so cloud is the only executor.
		return $skip || Bridge::is_paired();
	}

	/**
	 * Synchronous handler — keeps work tiny: just enqueue an AS job.
	 */
	public static function on_event_resolved( string $event, array $payload, array $trigger ): void {
		if ( ! Bridge::is_paired() ) {
			return;
		}
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		$envelope = [
			'event_type'    => $event,
			'payload'       => $payload,
			'site_event_id' => self::generate_site_event_id( $event, $trigger ),
			'trigger'       => [
				'workflow_id'         => (int) ( $trigger['workflow_id'] ?? 0 ),
				'workflow_version_id' => (int) ( $trigger['workflow_version_id'] ?? 0 ),
				'app'                 => (string) ( $trigger['app'] ?? '' ),
			],
			'fired_at' => time(),
		];

		as_enqueue_async_action( self::HOOK_FORWARD, [ 'envelope' => $envelope ], self::GROUP );
	}

	/**
	 * Async handler — actually POSTs to the cloud's ingest endpoint.
	 */
	public static function process_forward( $envelope ): void {
		if ( ! is_array( $envelope ) ) {
			return;
		}
		if ( ! Bridge::is_paired() ) {
			return;
		}

		$state = Bridge::get_state();
		$endpoint = (string) ( $state['ingest_endpoint'] ?? '' );
		if ( '' === $endpoint ) {
			return;
		}

		$body = [
			'event_type'    => $envelope['event_type'] ?? '',
			'site_event_id' => $envelope['site_event_id'] ?? null,
			'payload'       => array_merge(
				(array) ( $envelope['payload'] ?? [] ),
				[ '_zaplane_meta' => $envelope['trigger'] ?? [] ]
			),
		];

		$res = HttpClient::post_signed(
			$endpoint,
			$body,
			(int) $state['site_id'],
			(string) $state['site_secret'],
			15
		);

		// Surface failures in the daemon's failure inbox via PHP error log,
		// so the existing /worker/failures view picks them up. Action
		// Scheduler will also retry on its own.
		if ( ! $res['ok'] ) {
			error_log( sprintf(
				'[zaplane] event forward failed: status=%d error=%s event_type=%s',
				$res['status'],
				$res['error'],
				$envelope['event_type'] ?? ''
			) );
			throw new \RuntimeException( 'Event forward failed: ' . $res['error'] );
		}
	}

	private static function generate_site_event_id( string $event, array $trigger ): string {
		// Deterministic per (workflow + event + microtime) so AS retries of
		// the same forward action share an id, but distinct triggers get
		// distinct ids — matches the cloud's idempotency contract.
		return substr(
			hash(
				'sha256',
				$event . '|' . ( $trigger['workflow_id'] ?? '' ) . '|' . ( $trigger['workflow_version_id'] ?? '' ) . '|' . microtime( true ) . '|' . wp_rand()
			),
			0,
			32
		);
	}
}
