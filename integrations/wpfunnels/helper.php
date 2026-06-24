<?php
namespace Zaplane\Integrations\Wpfunnels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Helper {

	private static function resolve_wpfunnels_trigger( string $event, array $args, array $config ) {
		switch ( $event ) {
			case 'import_complete':
			case 'template_body_top':
			case 'template_container_top':
			case 'template_container_bottom':
			case 'template_wp_footer':
				return self::resolve_generic_trigger_payload( $event, $args );

			case 'after_funnel_creation':
				$funnel_id = (int) ( $args[0] ?? 0 );
				if ( $funnel_id <= 0 || ! self::matches_selected_funnel( $config, $funnel_id ) ) {
					return false;
				}

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'funnel_id' => $funnel_id,
						'funnel'    => self::resolve_funnel_payload( $funnel_id ),
					]
				);

			case 'after_step_creation':
				$step_id = (int) ( $args[0] ?? 0 );
				if ( $step_id > 0 && ! self::matches_selected_step( $config, $step_id ) ) {
					return false;
				}

				$step_payload = $step_id > 0 ? self::resolve_step_payload( $step_id ) : null;
				$funnel_id    = (int) ( $step_payload['funnel_id'] ?? 0 );

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'step_id'   => $step_id,
						'step'      => $step_payload,
						'funnel_id' => $funnel_id,
						'funnel'    => $funnel_id > 0 ? self::resolve_funnel_payload( $funnel_id ) : null,
					]
				);

			case 'after_step_duplicate':
				$funnel_id = (int) ( $args[0] ?? 0 );
				$step_id   = (int) ( $args[1] ?? 0 );
				if ( ! self::matches_selected_funnel( $config, $funnel_id ) || ! self::matches_selected_step( $config, $step_id ) ) {
					return false;
				}

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'funnel_id' => $funnel_id,
						'step_id'   => $step_id,
						'funnel'    => self::resolve_funnel_payload( $funnel_id ),
						'step'      => self::resolve_step_payload( $step_id ),
					]
				);

			case 'funnel_journey_starts':
			case 'funnel_journey_end':
				$step_id   = (int) ( $args[0] ?? 0 );
				$funnel_id = (int) ( $args[1] ?? 0 );
				if ( ! self::matches_selected_step( $config, $step_id ) || ! self::matches_selected_funnel( $config, $funnel_id ) ) {
					return false;
				}

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'step_id'   => $step_id,
						'funnel_id' => $funnel_id,
						'step'      => self::resolve_step_payload( $step_id ),
						'funnel'    => self::resolve_funnel_payload( $funnel_id ),
					]
				);

			case 'funnel_order_placed':
				$order_value = $args[0] ?? 0;
				$funnel_id   = (int) ( $args[1] ?? 0 );
				$step_id     = (int) ( $args[2] ?? 0 );
				if ( ! self::matches_selected_step( $config, $step_id ) || ! self::matches_selected_funnel( $config, $funnel_id ) ) {
					return false;
				}

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'order_id'  => self::resolve_order_id_from_value( $order_value ),
						'order'     => self::resolve_order_summary_from_value( $order_value ),
						'funnel_id' => $funnel_id,
						'step_id'   => $step_id,
						'funnel'    => self::resolve_funnel_payload( $funnel_id ),
						'step'      => self::resolve_step_payload( $step_id ),
					]
				);

			case 'order_bump_accepted':
			case 'order_bump_rejected':
				$step_id    = (int) ( $args[0] ?? 0 );
				$product_id = (int) ( $args[1] ?? 0 );
				if ( ! self::matches_selected_step( $config, $step_id ) ) {
					return false;
				}

				$funnel_id = self::resolve_funnel_id_from_step( $step_id );

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'step_id'    => $step_id,
						'product_id' => $product_id,
						'funnel_id'  => $funnel_id,
						'step'       => self::resolve_step_payload( $step_id ),
						'funnel'     => $funnel_id > 0 ? self::resolve_funnel_payload( $funnel_id ) : null,
					]
				);

			case 'offer_accepted':
			case 'offer_rejected':
				$order_value   = $args[0] ?? null;
				$offer_product = self::normalize_payload_value( $args[1] ?? [] );
				$step_id       = (int) ( $offer_product['step_id'] ?? ( $offer_product['stepId'] ?? 0 ) );
				if ( ! self::matches_selected_step( $config, $step_id ) ) {
					return false;
				}

				$funnel_id = self::resolve_funnel_id_from_step( $step_id );

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'order_id'       => self::resolve_order_id_from_value( $order_value ),
						'order'          => self::resolve_order_summary_from_value( $order_value ),
						'offer_product'  => $offer_product,
						'step_id'        => $step_id,
						'funnel_id'      => $funnel_id,
						'step'           => self::resolve_step_payload( $step_id ),
						'funnel'         => $funnel_id > 0 ? self::resolve_funnel_payload( $funnel_id ) : null,
					]
				);

			case 'child_order_created':
				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'parent_order'   => self::resolve_order_summary_from_value( $args[0] ?? null ),
						'child_order'    => self::resolve_order_summary_from_value( $args[1] ?? null ),
						'transaction_id' => self::normalize_payload_value( $args[2] ?? '' ),
					]
				);

			case 'subscription_created':
				$subscription = self::normalize_payload_value( $args[0] ?? null );
				$offer_product = self::normalize_payload_value( $args[1] ?? [] );
				$order_value  = $args[2] ?? null;
				$step_id      = (int) ( $offer_product['step_id'] ?? ( $offer_product['stepId'] ?? 0 ) );
				if ( ! self::matches_selected_step( $config, $step_id ) ) {
					return false;
				}
				$funnel_id = self::resolve_funnel_id_from_step( $step_id );

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'subscription'   => $subscription,
						'offer_product'  => $offer_product,
						'order_id'       => self::resolve_order_id_from_value( $order_value ),
						'order'          => self::resolve_order_summary_from_value( $order_value ),
						'step_id'        => $step_id,
						'funnel_id'      => $funnel_id,
						'step'           => self::resolve_step_payload( $step_id ),
						'funnel'         => $funnel_id > 0 ? self::resolve_funnel_payload( $funnel_id ) : null,
					]
				);

			case 'setup_wizard_complete':
				$funnel_id = (int) ( $args[0] ?? 0 );
				$action    = (string) ( $args[1] ?? '' );
				$goal      = (string) ( $args[2] ?? '' );
				if ( $funnel_id <= 0 || ! self::matches_selected_funnel( $config, $funnel_id ) ) {
					return false;
				}

				return self::resolve_generic_trigger_payload(
					$event,
					$args,
					[
						'funnel_id' => $funnel_id,
						'action'    => $action,
						'goal'      => $goal,
						'funnel'    => self::resolve_funnel_payload( $funnel_id ),
					]
				);
		}//end switch

		return false;
	}

	private static function resolve_generic_trigger_payload( string $event, array $args, array $extra = [] ): array {
		return array_merge(
			[
				'event'      => $event,
				'event_time' => current_time( 'mysql' ),
				'args'       => self::normalize_payload_value( array_slice( $args, 0, 4 ) ),
			],
			$extra
		);
	}

	private static function action_get_funnel_single( array $config, array $input ): array {
		$funnel_id = self::resolve_funnel_id_for_action( $config, $input );
		if ( $funnel_id <= 0 ) {
			return self::action_error( 'Funnel ID is required', $input );
		}

		$funnel = self::resolve_funnel_payload( $funnel_id );
		if ( ! $funnel ) {
			return self::action_error( 'Funnel not found', $input );
		}

		return self::action_success(
			array_merge(
				$input,
				[
					'funnel' => $funnel,
				]
			)
		);
	}

	private static function action_get_step_single( array $config, array $input ): array {
		$step_id = self::resolve_step_id_for_action( $config, $input );
		if ( $step_id <= 0 ) {
			return self::action_error( 'Step ID is required', $input );
		}

		$step = self::resolve_step_payload( $step_id );
		if ( ! $step ) {
			return self::action_error( 'Step not found', $input );
		}

		return self::action_success(
			array_merge(
				$input,
				[
					'step' => $step,
				]
			)
		);
	}

	private static function action_get_next_step( array $config, array $input ): array {
		$step_id = self::resolve_step_id_for_action( $config, $input );
		if ( $step_id <= 0 ) {
			return self::action_error( 'Step ID is required', $input );
		}

		$current_step = self::resolve_step_payload( $step_id );
		if ( ! $current_step ) {
			return self::action_error( 'Step not found', $input );
		}

		$next_step_id = (int) ( $current_step['next_step_id'] ?? 0 );
		if ( $next_step_id <= 0 ) {
			return self::action_error( 'Next step not found', $input );
		}

		$next_step = self::resolve_step_payload( $next_step_id );
		if ( ! $next_step ) {
			return self::action_error( 'Next step not found', $input );
		}

		return self::action_success(
			array_merge(
				$input,
				[
					'current_step' => $current_step,
					'next_step'    => $next_step,
				]
			)
		);
	}

	private static function action_add_action( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::action_error( 'Hook name is required', $input );
		}

		$accepted_args = (int) ( $config['accepted_args'] ?? 1 );
		if ( $accepted_args < 1 ) {
			$accepted_args = 1;
		}
		if ( $accepted_args > 99 ) {
			$accepted_args = 99;
		}

		add_action(
			$hook_name,
			static function () {
			},
			10,
			$accepted_args
		);

		return self::action_success(
			array_merge(
				$input,
				[
					'hook'          => $hook_name,
					'accepted_args' => $accepted_args,
					'registered'    => true,
					'event_time'    => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_do_action( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::action_error( 'Hook name is required', $input );
		}

		$arg_1 = $config['arg_1'] ?? null;
		$arg_2 = $config['arg_2'] ?? null;

		do_action( $hook_name, $arg_1, $arg_2 );

		return self::action_success(
			array_merge(
				$input,
				[
					'hook'       => $hook_name,
					'arg_1'      => $arg_1,
					'arg_2'      => $arg_2,
					'triggered'  => true,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_add_filter( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::action_error( 'Hook name is required', $input );
		}

		$accepted_args = (int) ( $config['accepted_args'] ?? 1 );
		if ( $accepted_args < 1 ) {
			$accepted_args = 1;
		}
		if ( $accepted_args > 99 ) {
			$accepted_args = 99;
		}

		$has_return_value = array_key_exists( 'return_value', $config );
		$return_value     = $config['return_value'] ?? null;

		$callback = static function ( $value = null ) use ( $has_return_value, $return_value ) {
			if ( $has_return_value ) {
				return $return_value;
			}
			return $value;
		};

		if ( function_exists( 'add_filter' ) ) {
			add_filter( $hook_name, $callback, 10, $accepted_args );
		} else {
			add_action( $hook_name, $callback, 10, $accepted_args );
		}

		return self::action_success(
			array_merge(
				$input,
				[
					'hook'          => $hook_name,
					'accepted_args' => $accepted_args,
					'registered'    => true,
					'event_time'    => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_apply_filters( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::action_error( 'Hook name is required', $input );
		}

		$value = $config['value'] ?? null;
		$arg_1 = $config['arg_1'] ?? null;
		$arg_2 = $config['arg_2'] ?? null;

		$filtered = apply_filters( $hook_name, $value, $arg_1, $arg_2 );

		return self::action_success(
			array_merge(
				$input,
				[
					'hook'           => $hook_name,
					'value'          => $value,
					'arg_1'          => $arg_1,
					'arg_2'          => $arg_2,
					'filtered_value' => $filtered,
					'event_time'     => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_remove_action( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::action_error( 'Hook name is required', $input );
		}

		$removed = false;
		if ( function_exists( 'remove_all_actions' ) ) {
			remove_all_actions( $hook_name );
			$removed = true;
		} elseif ( function_exists( 'remove_all_filters' ) ) {
			remove_all_filters( $hook_name );
			$removed = true;
		}

		return self::action_success(
			array_merge(
				$input,
				[
					'hook'       => $hook_name,
					'removed'    => $removed,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_has_action( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::action_error( 'Hook name is required', $input );
		}

		$priority = false;
		if ( function_exists( 'has_action' ) ) {
			$priority = has_action( $hook_name );
		}

		return self::action_success(
			array_merge(
				$input,
				[
					'hook'       => $hook_name,
					'exists'     => false !== $priority,
					'priority'   => false === $priority ? null : (int) $priority,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_current_filter( array $config, array $input ): array {
		$hook_name = trim( (string) ( $config['hook_name'] ?? '' ) );
		if ( '' === $hook_name ) {
			return self::action_error( 'Hook name is required', $input );
		}

		do_action( $hook_name );
		$current = function_exists( 'current_filter' ) ? (string) current_filter() : '';

		return self::action_success(
			array_merge(
				$input,
				[
					'hook'       => $hook_name,
					'current'    => $current,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_success( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	private static function action_error( string $message, array $input ): array {
		return [
			'port' => 'error',
			'data' => array_merge(
				$input,
				[
					'error' => $message,
				]
			),
		];
	}

	private static function resolve_funnel_payload( int $funnel_id ): ?array {
		$funnel_post = get_post( $funnel_id );
		if ( ! is_object( $funnel_post ) ) {
			return null;
		}

		$step_refs = self::get_funnel_steps( $funnel_id );
		$steps = [];

		foreach ( $step_refs as $step_ref ) {
			$step_id = 0;
			if ( is_array( $step_ref ) ) {
				$step_id = (int) ( $step_ref['id'] ?? 0 );
			} elseif ( is_numeric( $step_ref ) ) {
				$step_id = (int) $step_ref;
			}

			if ( $step_id <= 0 ) {
				continue;
			}

			$step_payload = self::resolve_step_payload( $step_id );
			if ( $step_payload ) {
				$steps[] = $step_payload;
			}
		}

		return [
			'funnel_id'     => $funnel_id,
			'funnel_title'  => (string) ( $funnel_post->post_title ?? '' ),
			'funnel_status' => (string) ( $funnel_post->post_status ?? '' ),
			'funnel_url'    => get_permalink( $funnel_id ),
			'total_steps'   => count( $steps ),
			'steps'         => $steps,
			'created_at'    => (string) ( $funnel_post->post_date ?? '' ),
			'updated_at'    => (string) ( $funnel_post->post_modified ?? '' ),
		];
	}

	private static function resolve_step_payload( int $step_id ): ?array {
		$step_post = get_post( $step_id );
		if ( ! is_object( $step_post ) ) {
			return null;
		}

		$step_type = self::meta_to_string( get_post_meta( $step_id, '_step_type', true ) );
		if ( '' === $step_type && class_exists( '\WpfunnelsTestStore' ) && method_exists( '\WpfunnelsTestStore', 'getStepType' ) ) {
			$step_type = (string) \WpfunnelsTestStore::getStepType( $step_id );
		}

		$funnel_id    = self::resolve_funnel_id_from_step( $step_id );
		$next_step_id = self::resolve_next_step_id( $funnel_id, $step_id );

		return [
			'step_id'      => $step_id,
			'funnel_id'    => $funnel_id,
			'step_type'    => $step_type,
			'step_title'   => (string) ( $step_post->post_title ?? '' ),
			'step_status'  => (string) ( $step_post->post_status ?? '' ),
			'step_url'     => get_permalink( $step_id ),
			'next_step_id' => $next_step_id,
			'created_at'   => (string) ( $step_post->post_date ?? '' ),
			'updated_at'   => (string) ( $step_post->post_modified ?? '' ),
		];
	}

	private static function get_funnel_steps( int $funnel_id ): array {
		if ( class_exists( '\WPFunnels\Wpfnl_functions' ) && method_exists( '\WPFunnels\Wpfnl_functions', 'get_steps' ) ) {
			$steps = \WPFunnels\Wpfnl_functions::get_steps( $funnel_id );
			return is_array( $steps ) ? $steps : [];
		}

		if ( class_exists( '\WpfunnelsTestStore' ) && method_exists( '\WpfunnelsTestStore', 'getSteps' ) ) {
			return (array) \WpfunnelsTestStore::getSteps( $funnel_id );
		}

		return [];
	}

	private static function resolve_funnel_id_from_step( int $step_id ): int {
		if ( class_exists( '\WPFunnels\Wpfnl_functions' ) && method_exists( '\WPFunnels\Wpfnl_functions', 'get_funnel_id_from_step' ) ) {
			$funnel_id = (int) \WPFunnels\Wpfnl_functions::get_funnel_id_from_step( $step_id );
			if ( $funnel_id > 0 ) {
				return $funnel_id;
			}
		}

		if ( class_exists( '\WpfunnelsTestStore' ) && method_exists( '\WpfunnelsTestStore', 'getFunnelIdFromStep' ) ) {
			$funnel_id = (int) \WpfunnelsTestStore::getFunnelIdFromStep( $step_id );
			if ( $funnel_id > 0 ) {
				return $funnel_id;
			}
		}

		return self::meta_to_int( get_post_meta( $step_id, '_funnel_id', true ) );
	}

	private static function resolve_next_step_id( int $funnel_id, int $step_id ): int {
		if ( $funnel_id > 0 && class_exists( '\WPFunnels\Wpfnl_functions' ) && method_exists( '\WPFunnels\Wpfnl_functions', 'get_next_step' ) ) {
			$next = \WPFunnels\Wpfnl_functions::get_next_step( $funnel_id, $step_id );
			if ( is_array( $next ) ) {
				$next_step_id = (int) ( $next['step_id'] ?? 0 );
				if ( $next_step_id > 0 ) {
					return $next_step_id;
				}
			}
		}

		if ( class_exists( '\WpfunnelsTestStore' ) && method_exists( '\WpfunnelsTestStore', 'getNextStepId' ) ) {
			return (int) \WpfunnelsTestStore::getNextStepId( $step_id );
		}

		return 0;
	}

	private static function resolve_order_id_from_value( $value ): int {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		if ( is_object( $value ) && method_exists( $value, 'get_id' ) ) {
			return (int) $value->get_id();
		}

		if ( is_array( $value ) ) {
			return (int) ( $value['id'] ?? 0 );
		}

		return 0;
	}

	private static function resolve_order_summary_from_value( $value ): ?array {
		$order = self::resolve_order_object( $value );
		$order_id = self::resolve_order_id_from_value( $value );

		if ( ! is_object( $order ) ) {
			if ( $order_id <= 0 ) {
				return null;
			}
			return [
				'id' => $order_id,
			];
		}

		if ( $order_id <= 0 && method_exists( $order, 'get_id' ) ) {
			$order_id = (int) $order->get_id();
		}

		return [
			'id'            => $order_id,
			'status'        => method_exists( $order, 'get_status' ) ? (string) $order->get_status() : '',
			'total'         => method_exists( $order, 'get_total' ) ? (float) $order->get_total() : 0,
			'currency'      => method_exists( $order, 'get_currency' ) ? (string) $order->get_currency() : '',
			'customer_id'   => method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0,
			'billing_email' => method_exists( $order, 'get_billing_email' ) ? (string) $order->get_billing_email() : '',
		];
	}

	private static function resolve_order_object( $value ) {
		if ( is_object( $value ) ) {
			return $value;
		}

		$order_id = self::resolve_order_id_from_value( $value );
		if ( $order_id <= 0 ) {
			return null;
		}

		if ( function_exists( '\wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				return $order;
			}
		}

		if ( class_exists( '\WpfunnelsTestStore' ) && method_exists( '\WpfunnelsTestStore', 'getOrder' ) ) {
			return \WpfunnelsTestStore::getOrder( $order_id );
		}

		return null;
	}

	private static function matches_selected_funnel( array $config, int $funnel_id ): bool {
		$selected_funnel = self::resolve_selected_id( $config, [ 'funnel_id' ] );
		if ( null === $selected_funnel ) {
			return true;
		}

		return $funnel_id > 0 && $selected_funnel === $funnel_id;
	}

	private static function matches_selected_step( array $config, int $step_id ): bool {
		$selected_step = self::resolve_selected_id( $config, [ 'step_id', 'checkout_step_id' ] );
		if ( null === $selected_step ) {
			return true;
		}

		return $step_id > 0 && $selected_step === $step_id;
	}

	private static function resolve_selected_id( array $config, array $keys ): ?int {
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $config ) ) {
				continue;
			}

			$value = $config[ $key ];
			if ( is_string( $value ) ) {
				$value = trim( $value );
			}

			if ( '' === $value || null === $value || 'any' === $value ) {
				return null;
			}

			return (int) $value;
		}

		return null;
	}

	private static function resolve_funnel_id_for_action( array $config, array $input ): int {
		$candidates = [
			$config['funnel_id'] ?? 0,
			$input['funnel_id'] ?? 0,
			is_array( $input['funnel'] ?? null ) ? ( $input['funnel']['funnel_id'] ?? ( $input['funnel']['id'] ?? 0 ) ) : 0,
			is_array( $input['step'] ?? null ) ? ( $input['step']['funnel_id'] ?? 0 ) : 0,
			is_array( $input['current_step'] ?? null ) ? ( $input['current_step']['funnel_id'] ?? 0 ) : 0,
			is_array( $input['next_step'] ?? null ) ? ( $input['next_step']['funnel_id'] ?? 0 ) : 0,
		];

		foreach ( $candidates as $candidate ) {
			$funnel_id = (int) $candidate;
			if ( $funnel_id > 0 ) {
				return $funnel_id;
			}
		}

		return 0;
	}

	private static function resolve_step_id_for_action( array $config, array $input ): int {
		$candidates = [
			$config['step_id'] ?? 0,
			$input['step_id'] ?? 0,
			is_array( $input['step'] ?? null ) ? ( $input['step']['step_id'] ?? ( $input['step']['id'] ?? 0 ) ) : 0,
			is_array( $input['current_step'] ?? null ) ? ( $input['current_step']['step_id'] ?? ( $input['current_step']['id'] ?? 0 ) ) : 0,
			is_array( $input['next_step'] ?? null ) ? ( $input['next_step']['step_id'] ?? ( $input['next_step']['id'] ?? 0 ) ) : 0,
		];

		foreach ( $candidates as $candidate ) {
			$step_id = (int) $candidate;
			if ( $step_id > 0 ) {
				return $step_id;
			}
		}

		return 0;
	}

	private static function normalize_payload_value( $value, int $depth = 0 ) {
		if ( $depth >= 4 ) {
			return null;
		}

		if ( null === $value || is_scalar( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			$normalized = [];
			$count = 0;

			foreach ( $value as $key => $item ) {
				if ( $count >= 20 ) {
					break;
				}
				$normalized[ $key ] = self::normalize_payload_value( $item, $depth + 1 );
				++$count;
			}

			return $normalized;
		}

		if ( is_object( $value ) ) {
			$base = [
				'class' => get_class( $value ),
			];

			if ( method_exists( $value, 'get_id' ) ) {
				$base['id'] = (int) $value->get_id();
			} elseif ( isset( $value->ID ) ) {
				$base['id'] = (int) $value->ID;
			}

			if ( method_exists( $value, 'toArray' ) ) {
				$data = $value->toArray();
				if ( is_array( $data ) ) {
					$base['data'] = self::normalize_payload_value( $data, $depth + 1 );
				}
			}

			return $base;
		}

		return null;
	}

	public static function query_funnels( $q ): array {
		unset( $q );

		$options = [
			[
				'name'  => 'any',
				'label' => 'Any Funnel',
			],
		];

		if ( class_exists( '\WpfunnelsTestStore' ) && method_exists( '\WpfunnelsTestStore', 'getFunnels' ) ) {
			$funnels = (array) \WpfunnelsTestStore::getFunnels();
			foreach ( $funnels as $funnel ) {
				$options[] = [
					'name'  => (string) ( $funnel['id'] ?? '' ),
					'label' => (string) ( $funnel['title'] ?? '' ),
				];
			}

			return $options;
		}

		$post_type = defined( 'WPFNL_FUNNELS_POST_TYPE' ) ? WPFNL_FUNNELS_POST_TYPE : 'wpfunnels';
		$posts = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => [ 'publish', 'draft', 'private' ],
				'posts_per_page' => 50,
			]
		);

		foreach ( $posts as $post ) {
			$options[] = [
				'name'  => (string) ( $post->ID ?? '' ),
				'label' => (string) ( $post->post_title ?? '' ),
			];
		}

		return $options;
	}

	public static function query_steps( $q ): array {
		unset( $q );

		$options = [
			[
				'name'  => 'any',
				'label' => 'Any Step',
			],
		];

		if ( class_exists( '\WpfunnelsTestStore' ) && method_exists( '\WpfunnelsTestStore', 'getSteps' ) ) {
			$steps = (array) \WpfunnelsTestStore::getSteps();
			foreach ( $steps as $step ) {
				$step_type = (string) ( $step['step_type'] ?? '' );
				$label = (string) ( $step['title'] ?? '' );
				if ( '' !== $step_type ) {
					$label .= ' (' . $step_type . ')';
				}

				$options[] = [
					'name'  => (string) ( $step['id'] ?? '' ),
					'label' => $label,
				];
			}

			return $options;
		}

		$post_type = defined( 'WPFNL_STEPS_POST_TYPE' ) ? WPFNL_STEPS_POST_TYPE : 'wpfunnel_steps';
		$posts = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => [ 'publish', 'draft', 'private' ],
				'posts_per_page' => 100,
			]
		);

		foreach ( $posts as $post ) {
			$step_type = self::meta_to_string( get_post_meta( (int) ( $post->ID ?? 0 ), '_step_type', true ) );
			$label = (string) ( $post->post_title ?? '' );
			if ( '' !== $step_type ) {
				$label .= ' (' . $step_type . ')';
			}

			$options[] = [
				'name'  => (string) ( $post->ID ?? '' ),
				'label' => $label,
			];
		}

		return $options;
	}

	private static function meta_to_string( $value ): string {
		if ( is_array( $value ) ) {
			if ( self::is_assoc_array( $value ) ) {
				return '';
			}
			$value = array_values( $value );
			$value = $value[0] ?? '';

			while ( is_array( $value ) ) {
				if ( self::is_assoc_array( $value ) ) {
					return '';
				}
				$value = array_values( $value );
				$value = $value[0] ?? '';
			}
		}

		if ( null === $value || false === $value ) {
			return '';
		}

		return (string) $value;
	}

	private static function meta_to_int( $value ): int {
		if ( is_array( $value ) ) {
			if ( self::is_assoc_array( $value ) ) {
				return 0;
			}
			$value = array_values( $value );
			$value = $value[0] ?? 0;

			while ( is_array( $value ) ) {
				if ( self::is_assoc_array( $value ) ) {
					return 0;
				}
				$value = array_values( $value );
				$value = $value[0] ?? 0;
			}
		}

		return (int) $value;
	}

	private static function is_assoc_array( array $value ): bool {
		return array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}
}
