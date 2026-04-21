<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ActionScheduler extends IntegrationBase {

	public static function get_slug(): string {
		return 'actionscheduler';
	}

	public static function get_name(): string {
		return 'Action Scheduler';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function show_trigger(): bool {
		return true;
	}

	public static function get_icon(): string {
		return 'action-scheduler.svg';
	}

	// -------------------------------------------------------------------------
	// Triggers
	// -------------------------------------------------------------------------

	public static function get_triggers(): array {
		return [
			'on_interval' => [
				'label' => 'On Interval Schedule',
				'hook'  => 'zaplane_as_interval_trigger',
			],
			'on_cron'     => [
				'label' => 'On Cron Schedule',
				'hook'  => 'zaplane_as_cron_trigger',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'on_interval' === $trigger ) {
			return [
				[
					'key'         => 'interval_amount',
					'label'       => 'Run Every',
					'type'        => 'number',
					'required'    => true,
					'default'     => 1,
					'placeholder' => '1',
					'description' => 'How often to run this workflow.',
				],
				[
					'key'      => 'interval_unit',
					'label'    => 'Unit',
					'type'     => 'select',
					'required' => true,
					'default'  => 'hours',
					'options'  => [
						[ 'value' => 'minutes', 'label' => 'Minutes' ],
						[ 'value' => 'hours',   'label' => 'Hours'   ],
						[ 'value' => 'days',    'label' => 'Days'    ],
						[ 'value' => 'weeks',   'label' => 'Weeks'   ],
					],
				],
			];
		}

		if ( 'on_cron' === $trigger ) {
			return [
				[
					'key'         => 'cron_expression',
					'label'       => 'Cron Expression',
					'type'        => 'expression',
					'required'    => true,
					'placeholder' => '0 * * * *',
					'description' => 'Standard cron format: minute hour day month weekday. Example: "0 * * * *" runs every hour.',
				],
			];
		}

		return [];
	}

	/**
	 * Called by trigger_router() for every active workflow that shares this hook.
	 * $node['_workflow_id'] is injected by trigger_router() so we can filter to
	 * only the workflow that this specific AS action was scheduled for.
	 */
	public static function resolve_trigger( array $node, array $hook_args ) {
		$as_args     = $hook_args[0] ?? [];
		$workflow_id = is_array( $as_args ) ? ( $as_args['workflow_id'] ?? null ) : null;

		if ( ! $workflow_id ) {
			return false;
		}

		// Only match the workflow this AS action was scheduled for.
		if ( (int) $workflow_id !== (int) ( $node['_workflow_id'] ?? 0 ) ) {
			return false;
		}

		return [
			'workflow_id'   => (int) $workflow_id,
			'schedule_type' => $as_args['schedule_type'] ?? ( $node['event'] ?? '' ),
			'triggered_at'  => current_time( 'mysql' ),
		];
	}

	// -------------------------------------------------------------------------
	// Actions
	// -------------------------------------------------------------------------

	public static function get_actions(): array {
		return [
			'schedule_single'    => [ 'label' => 'Schedule Single Action'    ],
			'schedule_recurring' => [ 'label' => 'Schedule Recurring Action' ],
			'cancel_actions'     => [ 'label' => 'Cancel Scheduled Actions'  ],
			'has_scheduled'      => [ 'label' => 'Check If Action Is Scheduled' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$hook_field = [
			'key'         => 'hook',
			'label'       => 'Hook Name',
			'type'        => 'expression',
			'required'    => true,
			'placeholder' => 'my_custom_hook',
			'description' => 'The WordPress action hook that will be fired.',
		];

		$group_field = [
			'key'         => 'group',
			'label'       => 'Group',
			'type'        => 'expression',
			'required'    => false,
			'placeholder' => 'zaplane',
			'description' => 'Optional group name for organizing actions.',
		];

		$args_field = [
			'key'         => 'args',
			'label'       => 'Arguments (JSON)',
			'type'        => 'expression',
			'required'    => false,
			'placeholder' => '{"key": "value"}',
			'description' => 'JSON-encoded arguments to pass to the action hook.',
		];

		switch ( $action ) {
			case 'schedule_single':
				return [
					$hook_field,
					[
						'key'         => 'scheduled_at',
						'label'       => 'Schedule At',
						'type'        => 'expression',
						'required'    => true,
						'placeholder' => '+1 hour',
						'description' => 'Unix timestamp or a strtotime-compatible string (e.g. "+1 hour", "+2 days").',
					],
					$args_field,
					$group_field,
				];

			case 'schedule_recurring':
				return [
					$hook_field,
					[
						'key'         => 'interval_amount',
						'label'       => 'Repeat Every',
						'type'        => 'number',
						'required'    => true,
						'default'     => 1,
						'placeholder' => '1',
					],
					[
						'key'      => 'interval_unit',
						'label'    => 'Unit',
						'type'     => 'select',
						'required' => true,
						'default'  => 'hours',
						'options'  => [
							[ 'value' => 'minutes', 'label' => 'Minutes' ],
							[ 'value' => 'hours',   'label' => 'Hours'   ],
							[ 'value' => 'days',    'label' => 'Days'    ],
							[ 'value' => 'weeks',   'label' => 'Weeks'   ],
						],
					],
					[
						'key'         => 'first_run',
						'label'       => 'First Run At',
						'type'        => 'expression',
						'required'    => false,
						'placeholder' => 'now',
						'description' => 'When to start. Leave empty to start immediately. Accepts strtotime-compatible strings.',
					],
					$args_field,
					$group_field,
				];

			case 'cancel_actions':
				return [
					$hook_field,
					$args_field,
					$group_field,
				];

			case 'has_scheduled':
				return [
					$hook_field,
					$args_field,
					$group_field,
				];
		}

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = $node['data']['event'] ?? '';
		$config = $node['data']['config'] ?? [];

		switch ( $event ) {
			case 'schedule_single':
				return self::execute_schedule_single( $config, $input );
			case 'schedule_recurring':
				return self::execute_schedule_recurring( $config, $input );
			case 'cancel_actions':
				return self::execute_cancel_actions( $config, $input );
			case 'has_scheduled':
				return self::execute_has_scheduled( $config, $input );
			default:
				return [ 'port' => 'main', 'data' => $input ];
		}
	}

	// -------------------------------------------------------------------------
	// Action Executors
	// -------------------------------------------------------------------------

	private static function execute_schedule_single( array $config, array $input ): array {
		$hook        = trim( $config['hook'] ?? '' );
		$scheduled   = trim( $config['scheduled_at'] ?? '' );
		$group       = trim( $config['group'] ?? 'zaplane' );
		$args_raw    = $config['args'] ?? '';

		if ( ! $hook ) {
			throw new \Exception( 'Action Scheduler: hook name is required for schedule_single.' );
		}

		$timestamp = is_numeric( $scheduled )
			? (int) $scheduled
			: ( $scheduled ? strtotime( $scheduled ) : time() );

		if ( ! $timestamp || $timestamp < 0 ) {
			throw new \Exception( 'Action Scheduler: invalid scheduled_at value "' . esc_html( $scheduled ) . '".' );
		}

		$args = self::parse_args( $args_raw );

		$action_id = as_schedule_single_action( $timestamp, $hook, $args, $group );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'action_id'    => $action_id,
				'hook'         => $hook,
				'scheduled_at' => gmdate( 'Y-m-d H:i:s', $timestamp ),
				'group'        => $group,
			] ),
		];
	}

	private static function execute_schedule_recurring( array $config, array $input ): array {
		$hook         = trim( $config['hook'] ?? '' );
		$amount       = (int) ( $config['interval_amount'] ?? 1 );
		$unit         = $config['interval_unit'] ?? 'hours';
		$first_run    = trim( $config['first_run'] ?? '' );
		$group        = trim( $config['group'] ?? 'zaplane' );
		$args_raw     = $config['args'] ?? '';

		if ( ! $hook ) {
			throw new \Exception( 'Action Scheduler: hook name is required for schedule_recurring.' );
		}

		$interval = self::to_seconds( $amount, $unit );

		$timestamp = $first_run
			? ( is_numeric( $first_run ) ? (int) $first_run : strtotime( $first_run ) )
			: time();

		if ( ! $timestamp ) {
			$timestamp = time();
		}

		$args = self::parse_args( $args_raw );

		$action_id = as_schedule_recurring_action( $timestamp, $interval, $hook, $args, $group );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'action_id'       => $action_id,
				'hook'            => $hook,
				'interval_seconds'=> $interval,
				'first_run_at'    => gmdate( 'Y-m-d H:i:s', $timestamp ),
				'group'           => $group,
			] ),
		];
	}

	private static function execute_cancel_actions( array $config, array $input ): array {
		$hook     = trim( $config['hook'] ?? '' );
		$group    = trim( $config['group'] ?? '' );
		$args_raw = $config['args'] ?? '';

		if ( ! $hook ) {
			throw new \Exception( 'Action Scheduler: hook name is required for cancel_actions.' );
		}

		$args = $args_raw ? self::parse_args( $args_raw ) : [];

		as_unschedule_all_actions( $hook, $args, $group );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'cancelled_hook'  => $hook,
				'cancelled_group' => $group,
			] ),
		];
	}

	private static function execute_has_scheduled( array $config, array $input ): array {
		$hook     = trim( $config['hook'] ?? '' );
		$group    = trim( $config['group'] ?? '' );
		$args_raw = $config['args'] ?? '';

		if ( ! $hook ) {
			throw new \Exception( 'Action Scheduler: hook name is required for has_scheduled.' );
		}

		$args = $args_raw ? self::parse_args( $args_raw ) : [];

		$next_timestamp = as_next_scheduled_action( $hook, $args, $group );
		$is_scheduled   = false !== $next_timestamp;

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'is_scheduled' => $is_scheduled,
				'next_run_at'  => $is_scheduled ? gmdate( 'Y-m-d H:i:s', $next_timestamp ) : null,
				'hook'         => $hook,
			] ),
		];
	}

	// -------------------------------------------------------------------------
	// Static Helpers (called by Automation class for trigger lifecycle)
	// -------------------------------------------------------------------------

	/**
	 * Ensure an AS action is scheduled for a given workflow trigger.
	 * Called from Automation::dispatch_as_trigger_schedules().
	 */
	public static function ensure_scheduled( int $workflow_id, string $event, array $config ): void {
		if ( 'on_interval' === $event ) {
			$hook     = 'zaplane_as_interval_trigger';
			$amount   = (int) ( $config['interval_amount'] ?? 1 );
			$unit     = $config['interval_unit'] ?? 'hours';
			$interval = self::to_seconds( $amount, $unit );

			$args = [ [ 'workflow_id' => $workflow_id, 'schedule_type' => 'on_interval' ] ];

			if ( ! as_has_scheduled_action( $hook, $args, 'zaplane_trigger' ) ) {
				as_schedule_recurring_action( time(), $interval, $hook, $args, 'zaplane_trigger' );
			}

		} elseif ( 'on_cron' === $event ) {
			$hook  = 'zaplane_as_cron_trigger';
			$cron  = trim( $config['cron_expression'] ?? '0 * * * *' );
			$args  = [ [ 'workflow_id' => $workflow_id, 'schedule_type' => 'on_cron' ] ];

			if ( ! as_has_scheduled_action( $hook, $args, 'zaplane_trigger' ) ) {
				as_schedule_cron_action( time(), $cron, $hook, $args, 'zaplane_trigger' );
			}
		}
	}

	/**
	 * Cancel all AS trigger actions for a specific workflow.
	 * Called from Automation::cancel_as_trigger_for_workflow().
	 */
	public static function cancel_for_workflow( int $workflow_id ): void {
		foreach ( [ 'zaplane_as_interval_trigger', 'zaplane_as_cron_trigger' ] as $hook ) {
			$args = [ [ 'workflow_id' => $workflow_id, 'schedule_type' => 'on_interval' ] ];
			as_unschedule_all_actions( $hook, $args, 'zaplane_trigger' );

			$args = [ [ 'workflow_id' => $workflow_id, 'schedule_type' => 'on_cron' ] ];
			as_unschedule_all_actions( $hook, $args, 'zaplane_trigger' );
		}
	}

	// -------------------------------------------------------------------------
	// Internal Utilities
	// -------------------------------------------------------------------------

	private static function to_seconds( int $amount, string $unit ): int {
		$map = [
			'minutes' => 60,
			'hours'   => 3600,
			'days'    => 86400,
			'weeks'   => 604800,
		];
		return $amount * ( $map[ $unit ] ?? 3600 );
	}

	private static function parse_args( $raw ): array {
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : [];
		}
		return [];
	}
}
