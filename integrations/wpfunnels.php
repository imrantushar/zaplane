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

	public static function get_trigger_sample_output( string $trigger ): array {
		// Shared base samples mirroring resolve_funnel_payload() / resolve_step_payload()
		// / resolve_order_summary_from_value() shapes in the Wpfunnels Helper trait.
		$funnel = [
			'funnel_id'     => 10,
			'funnel_title'  => 'Sample Funnel',
			'funnel_status' => 'publish',
			'funnel_url'    => home_url( '/sample-funnel/' ),
			'total_steps'   => 3,
			'steps'         => [],
			'created_at'    => '2024-01-01 10:00:00',
			'updated_at'    => '2024-01-02 12:00:00',
		];

		$step = [
			'step_id'      => 21,
			'funnel_id'    => 10,
			'step_type'    => 'landing',
			'step_title'   => 'Sample Step',
			'step_status'  => 'publish',
			'step_url'     => home_url( '/sample-funnel/landing/' ),
			'next_step_id' => 22,
			'created_at'   => '2024-01-01 10:00:00',
			'updated_at'   => '2024-01-02 12:00:00',
		];

		$order = [
			'id'            => 123,
			'status'        => 'completed',
			'total'         => 49.99,
			'currency'      => 'USD',
			'customer_id'   => 1,
			'billing_email' => 'customer@example.com',
		];

		$offer_product = [
			'id'      => 55,
			'step_id' => 21,
			'name'    => 'Sample Offer Product',
		];

		$base = [
			'event'      => $trigger,
			'event_time' => current_time( 'mysql' ),
			'args'       => [],
		];

		// Explicit extras matching each resolve_wpfunnels_trigger() branch.
		$explicit = [
			'after_funnel_creation' => [
				'funnel_id' => 10,
				'funnel'    => $funnel,
			],
			'after_step_creation' => [
				'step_id'   => 21,
				'step'      => $step,
				'funnel_id' => 10,
				'funnel'    => $funnel,
			],
			'after_step_duplicate' => [
				'funnel_id' => 10,
				'step_id'   => 21,
				'funnel'    => $funnel,
				'step'      => $step,
			],
			'funnel_journey_starts' => [
				'step_id'   => 21,
				'funnel_id' => 10,
				'step'      => $step,
				'funnel'    => $funnel,
			],
			'funnel_journey_end' => [
				'step_id'   => 21,
				'funnel_id' => 10,
				'step'      => $step,
				'funnel'    => $funnel,
			],
			'funnel_order_placed' => [
				'order_id'  => 123,
				'order'     => $order,
				'funnel_id' => 10,
				'step_id'   => 21,
				'funnel'    => $funnel,
				'step'      => $step,
			],
			'order_bump_accepted' => [
				'step_id'    => 21,
				'product_id' => 55,
				'funnel_id'  => 10,
				'step'       => $step,
				'funnel'     => $funnel,
			],
			'order_bump_rejected' => [
				'step_id'    => 21,
				'product_id' => 55,
				'funnel_id'  => 10,
				'step'       => $step,
				'funnel'     => $funnel,
			],
			'offer_accepted' => [
				'order_id'      => 123,
				'order'         => $order,
				'offer_product' => $offer_product,
				'step_id'       => 21,
				'funnel_id'     => 10,
				'step'          => $step,
				'funnel'        => $funnel,
			],
			'offer_rejected' => [
				'order_id'      => 123,
				'order'         => $order,
				'offer_product' => $offer_product,
				'step_id'       => 21,
				'funnel_id'     => 10,
				'step'          => $step,
				'funnel'        => $funnel,
			],
			'child_order_created' => [
				'parent_order'   => $order,
				'child_order'    => array_merge( $order, [ 'id' => 124, 'status' => 'processing', 'total' => 19.99 ] ),
				'transaction_id' => 'txn_abc123',
			],
			'subscription_created' => [
				'subscription'  => [ 'id' => 900, 'status' => 'active' ],
				'offer_product' => $offer_product,
				'order_id'      => 123,
				'order'         => $order,
				'step_id'       => 21,
				'funnel_id'     => 10,
				'step'          => $step,
				'funnel'        => $funnel,
			],
			'setup_wizard_complete' => [
				'funnel_id' => 10,
				'action'    => 'complete',
				'goal'      => 'sell_products',
				'funnel'    => $funnel,
			],
		];

		$extra = $explicit[ $trigger ] ?? [];

		// Category fallbacks by event-name prefix for the generic/template triggers
		// (import_complete, template_*) and any future trigger.
		if ( empty( $extra ) ) {
			if ( 0 === strpos( $trigger, 'funnel_' ) || 0 === strpos( $trigger, 'step_' ) || 0 === strpos( $trigger, 'after_' ) ) {
				$extra = [
					'funnel_id' => 10,
					'step_id'   => 21,
					'funnel'    => $funnel,
					'step'      => $step,
				];
			} elseif ( 0 === strpos( $trigger, 'order_' ) || 0 === strpos( $trigger, 'optin_' ) ) {
				$extra = [
					'order_id'   => 123,
					'contact_id' => 1,
					'email'      => 'customer@example.com',
					'total'      => 49.99,
					'order'      => $order,
				];
			}
		}

		return array_merge( $base, $extra );
	}
}
