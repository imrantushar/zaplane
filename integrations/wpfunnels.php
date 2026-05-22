<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Wpfunnels\Helper;

class Wpfunnels extends IntegrationBase {

	use Helper;

	private const INTRODUCTION = 'Monitor funnel lifecycle, checkout journey, offer acceptance/rejection, and run hook-based automation actions for WPFunnels without webhooks.';

	public static function get_slug(): string {
		return 'wpfunnels';
	}

	public static function get_name(): string {
		return 'WPFunnels';
	}

	public static function get_icon(): string {
		return 'wpfunnels.svg';
	}

	public static function get_introduction(): string {
		return self::INTRODUCTION;
	}

	public static function get_output_ports(): array {
		return [ 'main', 'error' ];
	}

	public static function get_triggers(): array {
		return [
			'loaded' => [
				'label' => 'WPFunnels Loaded',
				'hook'  => 'wpfunnels/loaded',
			],
			'init' => [
				'label' => 'WPFunnels Initialized',
				'hook'  => 'wpfunnels/init',
			],
			'pro_init' => [
				'label' => 'WPFunnels Pro Initialized',
				'hook'  => 'wpfunnels/pro_init',
			],
			'import_complete' => [
				'label' => 'Import Complete',
				'hook'  => 'wpfunnels/wpfnl_import_complete',
			],
			'after_funnel_creation' => [
				'label' => 'After Funnel Creation',
				'hook'  => 'wpfunnels_after_funnel_creation',
			],
			'after_step_creation' => [
				'label' => 'After Step Creation',
				'hook'  => 'wpfunnels_after_step_creation',
			],
			'after_step_duplicate' => [
				'label' => 'After Step Duplicate',
				'hook'  => 'wpfunnels/after_step_duplicate',
			],
			'funnel_journey_starts' => [
				'label' => 'Funnel Journey Starts',
				'hook'  => 'wpfunnels/funnel_journey_starts',
			],
			'funnel_journey_end' => [
				'label' => 'Funnel Journey Ends',
				'hook'  => 'wpfunnels/funnel_journey_end',
			],
			'funnel_order_placed' => [
				'label' => 'Funnel Order Placed',
				'hook'  => 'wpfunnels/funnel_order_placed',
			],
			'order_bump_accepted' => [
				'label' => 'Order Bump Accepted',
				'hook'  => 'wpfunnels/order_bump_accepted',
			],
			'order_bump_rejected' => [
				'label' => 'Order Bump Rejected',
				'hook'  => 'wpfunnels/order_bump_rejected',
			],
			'offer_accepted' => [
				'label' => 'Offer Accepted',
				'hook'  => 'wpfunnels/offer_accepted',
			],
			'offer_rejected' => [
				'label' => 'Offer Rejected',
				'hook'  => 'wpfunnels/offer_rejected',
			],
			'child_order_created' => [
				'label' => 'Child Order Created',
				'hook'  => 'wpfunnels/child_order_created',
			],
			'subscription_created' => [
				'label' => 'Subscription Created',
				'hook'  => 'wpfunnels/subscription_created',
			],
			'setup_wizard_complete' => [
				'label' => 'Setup Wizard Completed',
				'hook'  => 'wpfunnels_setup_wizard_complete',
			],
			'template_body_top' => [
				'label' => 'Template Body Top',
				'hook'  => 'wpfunnels/template_body_top',
			],
			'template_container_top' => [
				'label' => 'Template Container Top',
				'hook'  => 'wpfunnels/template_container_top',
			],
			'template_container_bottom' => [
				'label' => 'Template Container Bottom',
				'hook'  => 'wpfunnels/template_container_bottom',
			],
			'template_wp_footer' => [
				'label' => 'Template Footer',
				'hook'  => 'wpfunnels/template_wp_footer',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		$step_triggers = [
			'funnel_journey_starts',
			'funnel_journey_end',
			'funnel_order_placed',
			'order_bump_accepted',
			'order_bump_rejected',
			'offer_accepted',
			'offer_rejected',
			'subscription_created',
			'after_step_duplicate',
		];

		if ( in_array( $trigger, $step_triggers, true ) ) {
			return [
				[
					'key'      => 'step_id',
					'label'    => 'Step',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'wpfunnels',
						'query'       => 'steps',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}

		$funnel_triggers = [
			'after_funnel_creation',
			'setup_wizard_complete',
		];

		if ( in_array( $trigger, $funnel_triggers, true ) ) {
			return [
				[
					'key'      => 'funnel_id',
					'label'    => 'Funnel',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'wpfunnels',
						'query'       => 'funnels',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$event  = (string) ( $node['event'] ?? '' );
		$config = (array) ( $node['data']['config'] ?? [] );
		if ( '' === $event ) {
			return false;
		}

		return self::resolve_wpfunnels_trigger( $event, $args, $config );
	}

	public static function get_actions(): array {
		return [
			'get_funnel_single' => [ 'label' => 'Get Funnel (Single)' ],
			'get_step_single'   => [ 'label' => 'Get Step (Single)' ],
			'get_next_step'     => [ 'label' => 'Get Next Step' ],
			'add_action'        => [ 'label' => 'Add Action' ],
			'do_action'         => [ 'label' => 'Do Action' ],
			'add_filter'        => [ 'label' => 'Add Filter' ],
			'apply_filters'     => [ 'label' => 'Apply Filters' ],
			'remove_action'     => [ 'label' => 'Remove Action' ],
			'has_action'        => [ 'label' => 'Has Action' ],
			'current_filter'    => [ 'label' => 'Current Filter' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$hook_field = [
			'key'         => 'hook_name',
			'label'       => 'Hook Name',
			'type'        => 'text',
			'placeholder' => 'wpfunnels/custom_hook',
			'required'    => true,
		];

		switch ( $action ) {
			case 'get_funnel_single':
				return [
					[
						'key'      => 'funnel_id',
						'label'    => 'Funnel',
						'type'     => 'select',
						'dynamic'  => [
							'integration' => 'wpfunnels',
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
							'integration' => 'wpfunnels',
							'query'       => 'steps',
							'select'      => [ 'name', 'label' ],
						],
						'required' => true,
					],
				];

			case 'add_action':
			case 'add_filter':
				return [
					$hook_field,
					[
						'key'         => 'accepted_args',
						'label'       => 'Accepted Args',
						'type'        => 'number',
						'placeholder' => '1',
					],
				];

			case 'do_action':
				return [
					$hook_field,
					[ 'key' => 'arg_1', 'label' => 'Argument 1', 'type' => 'text' ],
					[ 'key' => 'arg_2', 'label' => 'Argument 2', 'type' => 'text' ],
				];

			case 'apply_filters':
				return [
					$hook_field,
					[ 'key' => 'value', 'label' => 'Value', 'type' => 'text' ],
					[ 'key' => 'arg_1', 'label' => 'Argument 1', 'type' => 'text' ],
					[ 'key' => 'arg_2', 'label' => 'Argument 2', 'type' => 'text' ],
				];

			case 'remove_action':
			case 'has_action':
			case 'current_filter':
				return [ $hook_field ];
		}

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = (string) ( $node['data']['event'] ?? '' );
		$config = (array) ( $node['data']['config'] ?? [] );
		$method = 'action_' . $event;

		if ( '' !== $event && method_exists( static::class, $method ) ) {
			return static::{$method}( $config, $input );
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
