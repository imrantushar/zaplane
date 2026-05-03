<?php

namespace Zaplane\Framework\Classes;

use Zaplane\Models\Run;
use Zaplane\Models\Workflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages global context variable groups (wp, workflow, …).
 *
 * Adding a new group requires only two steps:
 *   1. Add an entry to self::$registry  →  'prefix' => 'build_prefix'
 *   2. Add the corresponding private static build_prefix( ?Run $run ): array method
 *
 * Everything else — detection, lazy building, picker output — is automatic.
 */
class GlobalContext {

	/**
	 * Registry of available context groups.
	 * Key   = the prefix used in expressions, e.g. {{wp.user_email}}
	 * Value = name of the static builder method on this class
	 */
	private static array $registry = [
		'wp'       => 'build_wp',
		'workflow' => 'build_workflow',
	];

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Scan a node config array for {{prefix.key}} expressions and return
	 * the unique non-numeric prefixes found.
	 * Numeric prefixes are node IDs (e.g. {{2.email}}), not global context.
	 *
	 * @param  array $config  $node['data']['config']
	 * @return string[]
	 */
	public static function detect_prefixes( array $config ): array {
		if ( empty( $config ) ) {
			return [];
		}

		$json = wp_json_encode( $config );
		if ( ! $json ) {
			return [];
		}

		preg_match_all( '/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\./', $json, $matches );

		return array_values(
			array_unique(
				array_filter(
					$matches[1] ?? [],
					fn( $p ) => ! is_numeric( $p ) && isset( self::$registry[ $p ] )
				)
			)
		);
	}

	/**
	 * Build context data for the given prefixes only.
	 * Called at execution time — only the groups actually used by the node are built.
	 *
	 * @param  string[] $prefixes  From detect_prefixes()
	 * @param  Run|null $run       Real execution: pass the Run model so wp user can be resolved.
	 *                             Test execution (execute-node): pass null → falls back to wp_get_current_user().
	 * @return array<string, array>  e.g. ['wp' => [...], 'workflow' => [...]]
	 */
	public static function build( array $prefixes, ?Run $run = null ): array {
		$context = [];

		foreach ( $prefixes as $prefix ) {
			$method = self::$registry[ $prefix ] ?? null;
			if ( $method && method_exists( static::class, $method ) ) {
				$context[ $prefix ] = static::$method( $run );
			}
		}

		return $context;
	}

	/**
	 * Return all groups formatted for the condition-variables picker.
	 * Always includes every registered group with sample values.
	 * Only used for the API response — not for execution.
	 *
	 * @param  Workflow|null $workflow  Pass the current workflow for real samples.
	 * @return array<string, array>
	 */
	public static function all_for_picker( ?Workflow $workflow = null ): array {
		$wpUser = wp_get_current_user();

		$context = [];

		if ( isset( self::$registry['workflow'] ) ) {
			$context['workflow'] = [
				'label'     => 'Workflow',
				'prefix'    => 'workflow',
				'variables' => [
					[ 'key' => 'workflow_id',     'type' => 'integer', 'sample' => $workflow ? $workflow->id : 0 ],
					[ 'key' => 'workflow_name',   'type' => 'string',  'sample' => $workflow ? ( $workflow->title ?? $workflow->name ?? '' ) : '' ],
					[ 'key' => 'workflow_status', 'type' => 'string',  'sample' => $workflow ? ( $workflow->status ?? 'active' ) : 'active' ],
				],
			];
		}

		if ( isset( self::$registry['wp'] ) ) {
			$context['wp'] = [
				'label'     => 'WordPress',
				'prefix'    => 'wp',
				'variables' => [
					[ 'key' => 'wp_version',       'type' => 'string',  'sample' => get_bloginfo( 'version' ) ],
					[ 'key' => 'user_id',          'type' => 'integer', 'sample' => (int) $wpUser->ID ],
					[ 'key' => 'username',         'type' => 'string',  'sample' => $wpUser->user_login ],
					[ 'key' => 'user_email',       'type' => 'string',  'sample' => $wpUser->user_email ],
					[ 'key' => 'timestamp',        'type' => 'string',  'sample' => current_time( 'mysql' ) ],
					[ 'key' => 'total_post_count', 'type' => 'integer', 'sample' => (int) wp_count_posts()->publish ],
				],
			];
		}

		return $context;
	}

	// -------------------------------------------------------------------------
	// Context builders — add new groups here
	// -------------------------------------------------------------------------

	/**
	 * WordPress context.
	 *
	 * In real execution the triggering user is resolved from __wp_user_id stored
	 * in run->trigger_data at hook-fire time (synchronous, correct user logged in).
	 * In test execution (run = null) falls back to wp_get_current_user() which is
	 * the admin calling the REST API — correct for testing.
	 */
	private static function build_wp( ?Run $run ): array {
		$wpUser = null;

		if ( $run ) {
			$triggerData  = is_array( $run->trigger_data ) ? $run->trigger_data : [];
			$storedUserId = $triggerData['__wp_user_id'] ?? 0;
			if ( $storedUserId ) {
				$wpUser = get_userdata( (int) $storedUserId );
			}
		}

		if ( ! $wpUser || ! $wpUser->ID ) {
			$wpUser = wp_get_current_user();
		}

		return [
			'wp_version'       => get_bloginfo( 'version' ),
			'user_id'          => $wpUser ? (int) $wpUser->ID : 0,
			'username'         => $wpUser ? $wpUser->user_login : '',
			'user_email'       => $wpUser ? $wpUser->user_email : '',
			'timestamp'        => current_time( 'mysql' ),
			'total_post_count' => (int) wp_count_posts()->publish,
		];
	}

	/**
	 * Workflow context.
	 */
	private static function build_workflow( ?Run $run ): array {
		$workflow = $run ? Workflow::find( $run->workflow_id ) : null;

		return [
			'workflow_id'     => $workflow ? $workflow->id : 0,
			'workflow_name'   => $workflow ? ( $workflow->title ?? $workflow->name ?? '' ) : '',
			'workflow_status' => $workflow ? ( $workflow->status ?? 'active' ) : '',
		];
	}
}
