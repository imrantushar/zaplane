<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Cartflows\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cartflows extends IntegrationBase {

	use Helper;

	public static function get_slug(): string {
		return 'cartflows';
	}

	public static function get_name(): string {
		return 'CartFlows';
	}

	public static function get_icon(): string {
		return 'cartflows.svg';
	}

	public static function get_triggers(): array {
		return [
			'cartflows_init' => [
				'label' => 'CartFlows Initialized',
				'hook'  => 'cartflows_init',
			],
			'cartflows_body_top' => [
				'label' => 'CartFlows Body Top',
				'hook'  => 'cartflows_body_top',
			],
			'cartflows_container_top' => [
				'label' => 'CartFlows Container Top',
				'hook'  => 'cartflows_container_top',
			],
			'cartflows_container_bottom' => [
				'label' => 'CartFlows Container Bottom',
				'hook'  => 'cartflows_container_bottom',
			],
			'cartflows_wp_footer' => [
				'label' => 'CartFlows Footer',
				'hook'  => 'cartflows_wp_footer',
			],
			'cartflows_import_complete' => [
				'label' => 'CartFlows Import Complete',
				'hook'  => 'cartflows_import_complete',
			],
			'step_viewed' => [
				'label' => 'CartFlows Step Viewed',
				'hook'  => 'cartflows_wp',
			],
			'checkout_before_shortcode' => [
				'label' => 'Checkout Before Shortcode',
				'hook'  => 'cartflows_checkout_before_shortcode',
			],
			'optin_before_shortcode' => [
				'label' => 'Opt-in Before Shortcode',
				'hook'  => 'cartflows_optin_before_shortcode',
			],
			'gutenberg_before_checkout_shortcode' => [
				'label' => 'Gutenberg Before Checkout Shortcode',
				'hook'  => 'cartflows_gutenberg_before_checkout_shortcode',
			],
			'gutenberg_checkout_options_filters' => [
				'label' => 'Gutenberg Checkout Options Filters',
				'hook'  => 'cartflows_gutenberg_checkout_options_filters',
			],
			'gutenberg_optin_options_filters' => [
				'label' => 'Gutenberg Opt-in Options Filters',
				'hook'  => 'cartflows_gutenberg_optin_options_filters',
			],
			'checkout_review_init' => [
				'label' => 'Checkout Review Init',
				'hook'  => 'cartflows_woo_checkout_update_order_review_init',
			],
			'checkout_review_updated' => [
				'label' => 'Checkout Review Updated',
				'hook'  => 'cartflows_woo_checkout_update_order_review',
			],
			'template_imported' => [
				'label' => 'Template Imported',
				'hook'  => 'cartflows_after_template_import',
			],
			'instant_thankyou_before' => [
				'label' => 'Instant Thank You Before',
				'hook'  => 'cartflows_instant_thankyou_before',
			],
			'instant_thankyou_after' => [
				'label' => 'Instant Thank You After',
				'hook'  => 'cartflows_instant_thankyou_after',
			],
			'order_overview_cancelled' => [
				'label' => 'Order Overview Cancelled',
				'hook'  => 'cartflows_woocommerce_order_overview_cancelled',
			],
			'order_create_wc' => [
				'label' => 'WooCommerce Order Creation',
				'hook'  => 'woocommerce_checkout_order_processed',
			],
			'pro_loaded' => [
				'label' => 'CartFlows Pro Loaded',
				'hook'  => 'cartflows_pro_loaded',
			],
			'pro_init' => [
				'label' => 'CartFlows Pro Init',
				'hook'  => 'cartflows_pro_init',
			],
			'order_started' => [
				'label' => 'Order Started',
				'hook'  => 'cartflows_order_started',
			],
			'order_status_change_to_main_order' => [
				'label' => 'Order Status Change To Main Order',
				'hook'  => 'cartflows_order_status_change_to_main_order',
			],
			'offer_accepted' => [
				'label' => 'Offer Accepted',
				'hook'  => 'cartflows_offer_accepted',
			],
			'offer_rejected' => [
				'label' => 'Offer Rejected',
				'hook'  => 'cartflows_offer_rejected',
			],
			'offer_child_order_created' => [
				'label' => 'Offer Child Order Created',
				'hook'  => 'cartflows_offer_child_order_created',
			],
			'offer_subscription_created' => [
				'label' => 'Offer Subscription Created',
				'hook'  => 'cartflows_offer_subscription_created',
			],
			'checkout_before_multistep_layout' => [
				'label' => 'Checkout Before Multistep Layout',
				'hook'  => 'cartflows_checkout_before_multistep_checkout_layout',
			],
			'checkout_after_multistep_layout' => [
				'label' => 'Checkout After Multistep Layout',
				'hook'  => 'cartflows_checkout_after_multistep_checkout_layout',
			],
			'pre_checkout_offer_item_added' => [
				'label' => 'Pre Checkout Offer Item Added',
				'hook'  => 'wcf_pre_checkout_offer_item_added',
			],
			'after_quantity_update' => [
				'label' => 'After Quantity Update',
				'hook'  => 'wcf_after_quantity_update',
			],
			'after_single_selection' => [
				'label' => 'After Single Selection',
				'hook'  => 'wcf_after_single_selection',
			],
			'after_multiple_selection' => [
				'label' => 'After Multiple Selection',
				'hook'  => 'wcf_after_multiple_selection',
			],
			'order_bump_item_added' => [
				'label' => 'Order Bump Item Added',
				'hook'  => 'wcf_order_bump_item_added',
			],
			'order_bump_item_removed' => [
				'label' => 'Order Bump Item Removed',
				'hook'  => 'wcf_order_bump_item_removed',
			],
			'after_order_bump_process' => [
				'label' => 'After Order Bump Process',
				'hook'  => 'wcf_after_order_bump_process',
			],
			'quick_view_selection' => [
				'label' => 'Quick View Selection',
				'hook'  => 'wcf_after_quick_view_selection',
			],
			'quick_view_title_before' => [
				'label' => 'Quick View Title Before',
				'hook'  => 'cartflows_quick_view_title_before',
			],
			'quick_view_title_after' => [
				'label' => 'Quick View Title After',
				'hook'  => 'cartflows_quick_view_title_after',
			],
			'quick_view_price_before' => [
				'label' => 'Quick View Price Before',
				'hook'  => 'cartflows_quick_view_price_before',
			],
			'quick_view_price_after' => [
				'label' => 'Quick View Price After',
				'hook'  => 'cartflows_quick_view_price_after',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		$step_trigger_events = [
			'step_viewed',
			'checkout_before_shortcode',
			'optin_before_shortcode',
			'gutenberg_before_checkout_shortcode',
			'checkout_review_init',
			'checkout_review_updated',
			'template_imported',
			'order_create_wc',
			'checkout_before_multistep_layout',
			'checkout_after_multistep_layout',
			'pre_checkout_offer_item_added',
		];

		if ( in_array( $trigger, $step_trigger_events, true ) ) {
			return [
				[
					'key'      => 'step_id',
					'label'    => 'Checkout Step',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'cartflows',
						'query'       => 'checkout_steps',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}

		return [];
	}

	public static function get_dynamic_queries(): array {
		return [
			'checkout_steps' => [ self::class, 'checkout_steps_query' ],
		];
	}

	public static function checkout_steps_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$options = [
			[
				'name'  => 'any',
				'label' => 'Any Checkout Step',
			],
		];

		if ( ! class_exists( '\WP_Query' ) ) {
			return $options;
		}

		$step_post_type = defined( 'CARTFLOWS_STEP_POST_TYPE' ) ? CARTFLOWS_STEP_POST_TYPE : 'cartflows_step';
		$limit          = isset( $q['limit'] ) ? max( 1, (int) $q['limit'] ) : 50;
		$search         = trim( (string) ( $q['search'] ?? '' ) );

		$args = [
			'post_type'      => $step_post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		];

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );
		$step_ids = is_array( $query->posts ) ? $query->posts : [];

		foreach ( $step_ids as $step_id ) {
			$step_id = (int) $step_id;
			if ( $step_id <= 0 ) {
				continue;
			}

			$step_type = '';
			if ( function_exists( '\wcf_get_step_type' ) ) {
				$step_type = (string) wcf_get_step_type( $step_id );
			}

			if ( '' !== $step_type && 'checkout' !== $step_type ) {
				continue;
			}

			$step = get_post( $step_id );
			if ( ! is_object( $step ) ) {
				continue;
			}

			$options[] = [
				'name'  => $step_id,
				'label' => sprintf( '%s (#%d)', (string) ( $step->post_title ?? 'Checkout Step' ), $step_id ),
			];
		}

		return $options;
	}

	public static function resolve_trigger( array $node, array $args ) {
		if ( ! self::is_cartflows_available() ) {
			return false;
		}

		$event = (string) ( $node['event'] ?? ( $node['data']['event'] ?? ( $node['config']['trigger'] ?? '' ) ) );
		if ( '' === $event ) {
			return false;
		}

		$config = $node['config'] ?? ( $node['data']['config'] ?? [] );
		if ( ! is_array( $config ) ) {
			$config = [];
		}

		switch ( $event ) {
			case 'cartflows_init':
				return [
					'event'      => $event,
					'event_time' => current_time( 'mysql' ),
				];

			case 'cartflows_body_top':
			case 'cartflows_container_top':
			case 'cartflows_container_bottom':
			case 'cartflows_wp_footer':
			case 'cartflows_import_complete':
			case 'pro_loaded':
			case 'pro_init':
				return self::resolve_generic_event_payload( $event, $args );

			case 'step_viewed':
				$step_id = (int) ( $args[0] ?? 0 );
				$step    = self::resolve_step_payload( $step_id );
				if ( ! $step ) {
					return false;
				}

				if ( ! self::matches_selected_step( $config, (int) ( $step['step_id'] ?? 0 ) ) ) {
					return false;
				}

				return array_merge(
					$step,
					[
						'event'      => $event,
						'event_time' => current_time( 'mysql' ),
					]
				);

			case 'checkout_review_init':
			case 'checkout_review_updated':
				$data = is_array( $args[0] ?? null ) ? $args[0] : [];

				$payload = [
					'event'         => $event,
					'event_time'    => current_time( 'mysql' ),
					'checkout_id'   => (int) ( $data['wcf_checkout_id'] ?? 0 ),
					'checkout_data' => $data,
				];

				if ( $payload['checkout_id'] > 0 ) {
					$step = self::resolve_step_payload( $payload['checkout_id'] );
					if ( $step ) {
						$payload['step'] = $step;
					}
				}

				if ( ! self::matches_selected_step( $config, (int) $payload['checkout_id'] ) ) {
					return false;
				}

				return $payload;

			case 'template_imported':
				$step_id       = (int) ( $args[0] ?? 0 );
				$step_response = $args[1] ?? [];
				$step          = self::resolve_step_payload( $step_id );

				if ( ! $step ) {
					return false;
				}

				if ( ! self::matches_selected_step( $config, (int) ( $step['step_id'] ?? 0 ) ) ) {
					return false;
				}

				return array_merge(
					$step,
					[
						'event'          => $event,
						'event_time'     => current_time( 'mysql' ),
						'template_data'  => is_array( $step_response ) ? $step_response : [ 'raw' => $step_response ],
					]
				);

			case 'checkout_before_shortcode':
			case 'optin_before_shortcode':
			case 'gutenberg_before_checkout_shortcode':
			case 'checkout_before_multistep_layout':
			case 'checkout_after_multistep_layout':
			case 'pre_checkout_offer_item_added':
				$payload = self::resolve_step_event_payload( $event, $args, $config );
				return $payload ?: false;

			case 'gutenberg_checkout_options_filters':
			case 'gutenberg_optin_options_filters':
				return self::resolve_generic_event_payload(
					$event,
					$args,
					[
						'options' => self::normalize_payload_value( $args[0] ?? [] ),
					]
				);

			case 'instant_thankyou_before':
			case 'instant_thankyou_after':
			case 'order_overview_cancelled':
				return self::resolve_order_event_payload( $event, $args );

			case 'order_create_wc':
				$order_payload = self::resolve_order_created_payload( $args );
				if ( ! $order_payload ) {
					return false;
				}

				if ( ! self::matches_selected_step( $config, (int) ( $order_payload['checkout_id'] ?? 0 ), [ 'step_id', 'checkout_step_id', 'form_id' ] ) ) {
					return false;
				}

				return $order_payload;

			case 'order_started':
				return self::resolve_order_event_payload( $event, $args );

			case 'order_status_change_to_main_order':
				return self::resolve_order_event_payload(
					$event,
					$args,
					[
						'new_status' => (string) ( $args[0] ?? '' ),
						'old_status' => (string) ( $args[1] ?? '' ),
					],
					2
				);

			case 'offer_accepted':
			case 'offer_rejected':
				return self::resolve_order_event_payload(
					$event,
					$args,
					[
						'offer_product' => self::normalize_payload_value( $args[1] ?? [] ),
					]
				);

			case 'offer_child_order_created':
				$parent = self::resolve_order_summary_from_value( $args[0] ?? null );
				$child  = self::resolve_order_summary_from_value( $args[1] ?? null );

				return self::resolve_generic_event_payload(
					$event,
					$args,
					[
						'parent_order'   => $parent,
						'child_order'    => $child,
						'transaction_id' => self::normalize_payload_value( $args[2] ?? '' ),
					]
				);

			case 'offer_subscription_created':
				return self::resolve_order_event_payload(
					$event,
					$args,
					[
						'subscription'  => self::normalize_payload_value( $args[0] ?? null ),
						'offer_product' => self::normalize_payload_value( $args[2] ?? null ),
					],
					1
				);

			case 'after_quantity_update':
			case 'after_single_selection':
			case 'after_multiple_selection':
			case 'order_bump_item_added':
			case 'order_bump_item_removed':
			case 'quick_view_selection':
			case 'quick_view_title_before':
			case 'quick_view_title_after':
			case 'quick_view_price_before':
			case 'quick_view_price_after':
				return self::resolve_generic_event_payload(
					$event,
					$args,
					[
						'item_id' => (int) ( $args[0] ?? 0 ),
					]
				);

			case 'after_order_bump_process':
				return self::resolve_generic_event_payload(
					$event,
					$args,
					[
						'order_bump' => self::normalize_payload_value( $args[0] ?? [] ),
					]
				);
		}//end switch

		return false;
	}

	public static function get_actions(): array {
		return [
			'get_flow_single' => [
				'label' => 'Get Flow (Single)',
			],
			'get_step_single' => [
				'label' => 'Get Step (Single)',
			],
			'get_next_step' => [
				'label' => 'Get Next Step',
			],
			'add_action' => [
				'label' => 'Add Action Hook',
			],
			'do_action' => [
				'label' => 'Do Action Hook',
			],
			'add_filter' => [
				'label' => 'Add Filter Hook',
			],
			'apply_filters' => [
				'label' => 'Apply Filters Hook',
			],
			'remove_action' => [
				'label' => 'Remove Hook Callbacks',
			],
			'has_action' => [
				'label' => 'Has Hook Callback',
			],
			'current_filter' => [
				'label' => 'Get Current Filter',
			],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
			'get_flow_single' => [
				[
					'key'      => 'flow_id',
					'label'    => 'Flow ID',
					'type'     => 'expression',
					'required' => true,
				],
			],
			'get_step_single' => [
				[
					'key'      => 'step_id',
					'label'    => 'Step ID',
					'type'     => 'expression',
					'required' => true,
				],
			],
			'get_next_step' => [
				[
					'key'      => 'step_id',
					'label'    => 'Current Step ID',
					'type'     => 'expression',
					'required' => true,
				],
			],
			'add_action' => [
				[
					'key'      => 'hook_name',
					'label'    => 'Hook Name',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'   => 'accepted_args',
					'label' => 'Accepted Args',
					'type'  => 'number',
				],
			],
			'do_action' => [
				[
					'key'      => 'hook_name',
					'label'    => 'Hook Name',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'   => 'arg_1',
					'label' => 'Argument 1',
					'type'  => 'expression',
				],
				[
					'key'   => 'arg_2',
					'label' => 'Argument 2',
					'type'  => 'expression',
				],
			],
			'add_filter' => [
				[
					'key'      => 'hook_name',
					'label'    => 'Hook Name',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'   => 'return_value',
					'label' => 'Return Value',
					'type'  => 'expression',
				],
				[
					'key'   => 'accepted_args',
					'label' => 'Accepted Args',
					'type'  => 'number',
				],
			],
			'apply_filters' => [
				[
					'key'      => 'hook_name',
					'label'    => 'Hook Name',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'   => 'value',
					'label' => 'Value',
					'type'  => 'expression',
				],
				[
					'key'   => 'arg_1',
					'label' => 'Argument 1',
					'type'  => 'expression',
				],
				[
					'key'   => 'arg_2',
					'label' => 'Argument 2',
					'type'  => 'expression',
				],
			],
			'remove_action' => [
				[
					'key'      => 'hook_name',
					'label'    => 'Hook Name',
					'type'     => 'text',
					'required' => true,
				],
			],
			'has_action' => [
				[
					'key'      => 'hook_name',
					'label'    => 'Hook Name',
					'type'     => 'text',
					'required' => true,
				],
			],
			'current_filter' => [],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		if ( ! self::is_cartflows_available() ) {
			return self::error_response( 'CartFlows is not available', $input );
		}

		$event = (string) ( $node['data']['event'] ?? ( $node['event'] ?? ( $node['config']['action'] ?? '' ) ) );
		$config = $node['data']['config'] ?? ( $node['config']['data'] ?? [] );
		if ( ! is_array( $config ) ) {
			$config = [];
		}

		switch ( $event ) {
			case 'get_flow_single':
				return self::action_get_flow_single( $config, $input );
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
		}//end switch

		return self::main_response( $input );
	}
}
