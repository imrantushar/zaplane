<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manual trigger — a workflow that starts only when a person runs it.
 *
 * It hooks nothing (get_triggers has no `hook`), so it never fires on its own.
 * Instead the run is started on demand by the "Run" button / the
 * workflows/{id}/trigger endpoint, which calls Automation::run_workflow().
 * Great for on-demand jobs and for testing a workflow while building it.
 */
class Manual extends IntegrationBase {

	public static function get_slug(): string {
		return 'manual';
	}

	public static function get_name(): string {
		return 'Manual';
	}

	public static function get_category(): string {
		return 'core';
	}

	public static function get_icon(): string {
		return 'manual.svg';
	}

	public static function get_triggers(): array {
		return [
			'run_manually' => [ 'label' => 'Run Manually' ],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		return [
			[
				'key'   => 'note',
				'label' => 'How it runs',
				'type'  => 'copy',
				'value' => 'This workflow runs when you click “Run”, or via zaplane_run_workflow( id, data ).',
			],
		];
	}

	/**
	 * Fields available to later nodes. Any data passed to the manual run is also
	 * exposed, but these are always present.
	 */
	public static function get_trigger_sample_output( string $trigger ): array {
		return [
			'triggered_by' => 'manual',
			'triggered_at' => current_time( 'mysql' ),
			'user_id'      => get_current_user_id(),
		];
	}

	/**
	 * Manual runs start via Automation::run_workflow() with the payload passed
	 * straight through, so resolve_trigger just echoes whatever data it is given.
	 */
	public static function resolve_trigger( array $node, array $hook_args ) {
		$payload = $hook_args[0] ?? [];
		return is_array( $payload ) ? $payload : [ 'value' => $payload ];
	}
}
