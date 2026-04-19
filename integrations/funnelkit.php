<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Funnelkit\Helper;

class Funnelkit extends IntegrationBase {

	use Helper;

	private const INTRODUCTION = 'Track FunnelKit funnel and step lifecycle events, and run hook-based automation actions without webhooks.';

	public static function get_slug(): string {
		return 'funnelkit';
	}

	public static function get_name(): string {
		return 'FunnelKit';
	}

	public static function get_icon(): string {
		return 'funnelkit.svg';
	}

	public static function get_introduction(): string {
		return self::INTRODUCTION;
	}

	public static function get_output_ports(): array {
		return [ 'main', 'error' ];
	}

	public static function get_triggers(): array {
		return [
			'woofunnels_loaded' => [
				'label' => 'WooFunnels Loaded',
				'hook'  => 'woofunnels_loaded',
			],
			'core_modules_loaded' => [
				'label' => 'Core Modules Loaded',
				'hook'  => 'wffn_core_modules_loaded',
			],
			'loaded' => [
				'label' => 'FunnelKit Loaded',
				'hook'  => 'wffn_loaded',
			],
			'funnel_created' => [
				'label' => 'Funnel Created',
				'hook'  => 'wffn_funnel_created',
			],
			'duplicate_funnel' => [
				'label' => 'Funnel Duplicated',
				'hook'  => 'wffn_duplicate_funnel',
			],
			'funnel_imported' => [
				'label' => 'Funnel Imported',
				'hook'  => 'wffn_funnel_imported',
			],
			'funnel_updated' => [
				'label' => 'Funnel Updated',
				'hook'  => 'wffn_funnel_update',
			],
			'step_duplicated' => [
				'label' => 'Step Duplicated',
				'hook'  => 'wffn_step_duplicated',
			],
			'step_viewed' => [
				'label' => 'Step Viewed',
				'hook'  => 'wffn_event_step_viewed',
			],
			'step_converted' => [
				'label' => 'Step Converted',
				'hook'  => 'wffn_event_step_converted',
			],
			'funnel_ended' => [
				'label' => 'Funnel Ended',
				'hook'  => 'wffn_funnel_ended_event',
			],
			'ty_funnel_ended' => [
				'label' => 'Thank You Funnel Ended',
				'hook'  => 'wffn_ty_funnel_ended_event',
			],
			'import_completed' => [
				'label' => 'Import Completed',
				'hook'  => 'wffn_import_completed',
			],
			'importing_completed' => [
				'label' => 'Background Import Completed',
				'hook'  => 'wffn_importing_completed',
			],
			'template_import_remote' => [
				'label' => 'Template Import Remote',
				'hook'  => 'wffn_template_import_remote',
			],
			'container' => [
				'label' => 'Container Rendered',
				'hook'  => 'woofunnels_container',
			],
			'container_top' => [
				'label' => 'Container Top',
				'hook'  => 'woofunnels_container_top',
			],
			'container_bottom' => [
				'label' => 'Container Bottom',
				'hook'  => 'woofunnels_container_bottom',
			],
			'wp_footer' => [
				'label' => 'WooFunnels Footer',
				'hook'  => 'woofunnels_wp_footer',
			],
			'checkout_loaded' => [
				'label' => 'Checkout Module Loaded',
				'hook'  => 'wfacp_loaded',
			],
			'template_body_top' => [
				'label' => 'Template Body Top',
				'hook'  => 'wfacp_template_body_top',
			],
			'template_container_top' => [
				'label' => 'Template Container Top',
				'hook'  => 'wfacp_template_container_top',
			],
			'template_container_bottom' => [
				'label' => 'Template Container Bottom',
				'hook'  => 'wfacp_template_container_bottom',
			],
			'template_wp_footer' => [
				'label' => 'Template Footer',
				'hook'  => 'wfacp_template_wp_footer',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		$step_triggers = [
			'step_duplicated',
			'step_viewed',
			'step_converted',
		];

		if ( in_array( $trigger, $step_triggers, true ) ) {
			return [
				[
					'key'      => 'step_id',
					'label'    => 'Step',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'funnelkit',
						'query'       => 'steps',
						'select'      => [ 'name', 'label' ],
					],
				],
			];
		}

		$funnel_triggers = [
			'funnel_created',
			'duplicate_funnel',
			'funnel_imported',
			'funnel_updated',
			'funnel_ended',
			'ty_funnel_ended',
			'import_completed',
			'template_import_remote',
		];

		if ( in_array( $trigger, $funnel_triggers, true ) ) {
			return [
				[
					'key'      => 'funnel_id',
					'label'    => 'Funnel',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'funnelkit',
						'query'       => 'funnels',
						'select'      => [ 'name', 'label' ],
					],
				],
			];
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		if ( ! self::is_funnelkit_available() ) {
			return false;
		}

		$event = (string) ( $node['event'] ?? ( $node['data']['event'] ?? ( $node['config']['trigger'] ?? '' ) ) );
		if ( '' === $event ) {
			return false;
		}

		$config = $node['data']['config'] ?? ( $node['config'] ?? [] );
		if ( ! is_array( $config ) ) {
			$config = [];
		}

		return self::resolve_funnelkit_trigger( $event, $args, $config );
	}

	public static function get_actions(): array {
		return [
			'get_funnel_single' => [ 'label' => 'Get Funnel (Single)' ],
			'get_step_single'   => [ 'label' => 'Get Step (Single)' ],
			'get_next_step'     => [ 'label' => 'Get Next Step' ],
			'add_action'        => [ 'label' => 'Add Action Hook' ],
			'do_action'         => [ 'label' => 'Do Action Hook' ],
			'add_filter'        => [ 'label' => 'Add Filter Hook' ],
			'apply_filters'     => [ 'label' => 'Apply Filters Hook' ],
			'remove_action'     => [ 'label' => 'Remove All Hook Callbacks' ],
			'has_action'        => [ 'label' => 'Has Hook Callback' ],
			'current_filter'    => [ 'label' => 'Get Current Filter' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$hook_field = [
			'key'      => 'hook_name',
			'label'    => 'Hook Name',
			'type'     => 'text',
			'required' => true,
		];

		switch ( $action ) {
			case 'get_funnel_single':
				return [
					[
						'key'      => 'funnel_id',
						'label'    => 'Funnel',
						'type'     => 'select',
						'dynamic'  => [
							'integration' => 'funnelkit',
							'query'       => 'funnels',
							'select'      => [ 'name', 'label' ],
						],
						'required' => true,
					],
				];

			case 'get_step_single':
			case 'get_next_step':
				return [
					[
						'key'      => 'step_id',
						'label'    => 'Step',
						'type'     => 'select',
						'dynamic'  => [
							'integration' => 'funnelkit',
							'query'       => 'steps',
							'select'      => [ 'name', 'label' ],
						],
						'required' => true,
					],
				];

			case 'add_action':
				return [
					$hook_field,
					[
						'key'   => 'accepted_args',
						'label' => 'Accepted Args',
						'type'  => 'number',
					],
				];

			case 'do_action':
				return [
					$hook_field,
					[ 'key' => 'arg_1', 'label' => 'Argument 1', 'type' => 'expression' ],
					[ 'key' => 'arg_2', 'label' => 'Argument 2', 'type' => 'expression' ],
				];

			case 'add_filter':
				return [
					$hook_field,
					[ 'key' => 'return_value', 'label' => 'Return Value', 'type' => 'expression' ],
					[ 'key' => 'accepted_args', 'label' => 'Accepted Args', 'type' => 'number' ],
				];

			case 'apply_filters':
				return [
					$hook_field,
					[ 'key' => 'value', 'label' => 'Value', 'type' => 'expression' ],
					[ 'key' => 'arg_1', 'label' => 'Argument 1', 'type' => 'expression' ],
					[ 'key' => 'arg_2', 'label' => 'Argument 2', 'type' => 'expression' ],
				];

			case 'remove_action':
			case 'has_action':
				return [ $hook_field ];
		}

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		if ( ! self::is_funnelkit_available() ) {
			return self::action_error( 'FunnelKit is not available', $input );
		}

		$event = (string) ( $node['data']['event'] ?? ( $node['event'] ?? ( $node['config']['action'] ?? '' ) ) );
		$config = $node['data']['config'] ?? ( $node['config']['data'] ?? [] );
		if ( ! is_array( $config ) ) {
			$config = [];
		}

		switch ( $event ) {
			case 'get_funnel_single':
				return self::action_get_funnel_single( $config, $input );
			case 'get_step_single':
				return self::action_get_step_single( $config, $input );
			case 'get_next_step':
				return self::action_get_next_step( $config, $input );
			case 'add_action':
				return self::action_add_action( $config, $input );
			case 'do_action':
				return self::action_do_action( $config, $input );
			case 'add_filter':
				return self::action_add_filter( $config, $input );
			case 'apply_filters':
				return self::action_apply_filters( $config, $input );
			case 'remove_action':
				return self::action_remove_action( $config, $input );
			case 'has_action':
				return self::action_has_action( $config, $input );
			case 'current_filter':
				return self::action_current_filter( $input );
		}

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'funnels' => [ self::class, 'query_funnels' ],
			'steps'   => [ self::class, 'query_steps' ],
		];
	}
}
