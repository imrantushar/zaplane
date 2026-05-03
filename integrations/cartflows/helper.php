<?php
namespace Zaplane\Integrations\Cartflows;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Helper {

	private static function action_get_flow_single( array $config, array $input ): array {
		$flow_id = (int) ( $config['flow_id'] ?? 0 );
		if ( $flow_id <= 0 ) {
			return self::error_response( 'Flow ID is required', $input );
		}

		$flow = self::resolve_flow_payload( $flow_id );
		if ( ! $flow ) {
			return self::error_response( 'Flow not found', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'flow' => $flow,
				]
			)
		);
	}

	private static function action_get_step_single( array $config, array $input ): array {
		$step_id = (int) ( $config['step_id'] ?? 0 );
		if ( $step_id <= 0 ) {
			return self::error_response( 'Step ID is required', $input );
		}

		$step = self::resolve_step_payload( $step_id );
		if ( ! $step ) {
			return self::error_response( 'Step not found', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'step' => $step,
				]
			)
		);
	}

	private static function action_get_next_step( array $config, array $input ): array {
		$step_id = (int) ( $config['step_id'] ?? 0 );
		if ( $step_id <= 0 ) {
			return self::error_response( 'Step ID is required', $input );
		}

		$current_step = self::resolve_step_payload( $step_id );
		if ( ! $current_step ) {
			return self::error_response( 'Step not found', $input );
		}

		$next_step_id = (int) ( $current_step['next_step_id'] ?? 0 );
		if ( $next_step_id <= 0 ) {
			return self::error_response( 'Next step not found', $input );
		}

		$next_step = self::resolve_step_payload( $next_step_id );
		if ( ! $next_step ) {
			return self::error_response( 'Next step not found', $input );
		}

		return self::main_response(
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
			return self::error_response( 'Hook name is required', $input );
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

		return self::main_response(
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
			return self::error_response( 'Hook name is required', $input );
		}

		$arg_1 = $config['arg_1'] ?? null;
		$arg_2 = $config['arg_2'] ?? null;

		do_action( $hook_name, $arg_1, $arg_2 );

		return self::main_response(
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
			return self::error_response( 'Hook name is required', $input );
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

		return self::main_response(
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
			return self::error_response( 'Hook name is required', $input );
		}

		$value = $config['value'] ?? null;
		$arg_1 = $config['arg_1'] ?? null;
		$arg_2 = $config['arg_2'] ?? null;

		$filtered = apply_filters( $hook_name, $value, $arg_1, $arg_2 );

		return self::main_response(
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
			return self::error_response( 'Hook name is required', $input );
		}

		$removed = false;
		if ( function_exists( 'remove_all_actions' ) ) {
			remove_all_actions( $hook_name );
			$removed = true;
		} elseif ( function_exists( 'remove_all_filters' ) ) {
			remove_all_filters( $hook_name );
			$removed = true;
		}

		return self::main_response(
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
			return self::error_response( 'Hook name is required', $input );
		}

		$priority = false;
		if ( function_exists( 'has_action' ) ) {
			$priority = has_action( $hook_name );
		}

		return self::main_response(
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

	private static function action_current_filter( array $input ): array {
		$hook_name = function_exists( 'current_filter' ) ? (string) current_filter() : '';

		return self::main_response(
			array_merge(
				$input,
				[
					'hook'       => $hook_name,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function resolve_generic_event_payload( string $event, array $args, array $extra = [] ): array {
		$payload = array_merge(
			[
				'event'      => $event,
				'event_time' => current_time( 'mysql' ),
			],
			$extra
		);

		$payload['args'] = self::normalize_payload_value( array_slice( $args, 0, 3 ) );

		return $payload;
	}

	private static function resolve_step_event_payload( string $event, array $args, array $config ): ?array {
		$step_id = (int) ( $args[0] ?? 0 );
		if ( $step_id <= 0 ) {
			return null;
		}

		if ( ! self::matches_selected_step( $config, $step_id, [ 'step_id', 'checkout_step_id', 'form_id' ] ) ) {
			return null;
		}

		$step = self::resolve_step_payload( $step_id );

		$payload = [
			'event'      => $event,
			'event_time' => current_time( 'mysql' ),
			'step_id'    => $step_id,
		];

		if ( $step ) {
			$payload['step'] = $step;
		}

		if ( isset( $args[1] ) ) {
			$payload['arg_2'] = self::normalize_payload_value( $args[1] );
		}

		return $payload;
	}

	private static function resolve_order_event_payload( string $event, array $args, array $extra = [], int $order_arg_index = 0 ): array {
		$order_value = $args[ $order_arg_index ] ?? null;
		$order       = self::resolve_order_summary_from_value( $order_value );
		$order_id    = self::resolve_order_id_from_value( $order_value );

		return self::resolve_generic_event_payload(
			$event,
			$args,
			array_merge(
				[
					'order_id' => $order_id,
					'order'    => $order,
				],
				$extra
			)
		);
	}

	private static function resolve_order_summary_from_value( $value ): ?array {
		$order = self::resolve_order_from_value( $value );
		if ( ! is_object( $order ) ) {
			return null;
		}

		$order_id = method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0;
		return self::resolve_order_summary( $order, $order_id );
	}

	private static function resolve_order_id_from_value( $value ): int {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		$order = self::resolve_order_from_value( $value );
		if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
			return (int) $order->get_id();
		}

		return 0;
	}

	private static function resolve_order_from_value( $value ) {
		if ( is_object( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) && function_exists( '\wc_get_order' ) ) {
			return wc_get_order( (int) $value );
		}

		if ( is_array( $value ) ) {
			$maybe_id = (int) ( $value['id'] ?? 0 );
			if ( $maybe_id > 0 && function_exists( '\wc_get_order' ) ) {
				return wc_get_order( $maybe_id );
			}
		}

		return null;
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
			$count      = 0;
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

			if ( method_exists( $value, 'get_status' ) ) {
				$base['status'] = (string) $value->get_status();
			}

			if ( method_exists( $value, 'get_total' ) ) {
				$base['total'] = (float) $value->get_total();
			}

			if ( method_exists( $value, 'get_data' ) ) {
				$data = $value->get_data();
				if ( is_array( $data ) ) {
					$base['data'] = self::normalize_payload_value( $data, $depth + 1 );
				}
			}

			if ( ! isset( $base['data'] ) && method_exists( $value, 'toArray' ) ) {
				$data = $value->toArray();
				if ( is_array( $data ) ) {
					$base['data'] = self::normalize_payload_value( $data, $depth + 1 );
				}
			}

			return $base;
		}

		return null;
	}

	private static function resolve_order_created_payload( array $args ): ?array {
		$order_id = (int) ( $args[0] ?? 0 );
		$order    = self::resolve_order_object( $order_id, $args );
		if ( ! is_object( $order ) ) {
			return null;
		}

		if ( $order_id <= 0 && method_exists( $order, 'get_id' ) ) {
			$order_id = (int) $order->get_id();
		}

		if ( $order_id <= 0 ) {
			return null;
		}

		$meta_data  = self::resolve_order_meta_data( $order_id, $order );
		$checkout_id = 0;

		if ( method_exists( $order, 'get_meta' ) ) {
			$checkout_id = (int) $order->get_meta( '_wcf_checkout_id', true );
		}

		if ( $checkout_id <= 0 ) {
			$checkout_id = (int) ( $meta_data['wcf_checkout_id'] ?? 0 );
		}

		$posted_data = is_array( $args[1] ?? null ) ? $args[1] : [];

		return [
			'event'          => 'order_create_wc',
			'event_time'     => current_time( 'mysql' ),
			'order_id'       => $order_id,
			'checkout_id'    => $checkout_id,
			'order'          => self::resolve_order_summary( $order, $order_id ),
			'order_products' => self::resolve_order_products_payload( $order ),
			'meta_data'      => $meta_data,
			'posted_data'    => $posted_data,
		];
	}

	private static function resolve_order_object( int $order_id, array $args ) {
		$order = $args[2] ?? null;

		if ( ! is_object( $order ) ) {
			$order = $args[1] ?? null;
		}

		if ( ! is_object( $order ) && $order_id > 0 && function_exists( '\wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
		}

		return is_object( $order ) ? $order : null;
	}

	private static function resolve_order_summary( $order, int $order_id ): array {
		$status = method_exists( $order, 'get_status' ) ? (string) $order->get_status() : '';
		$total = method_exists( $order, 'get_total' ) ? (float) $order->get_total() : 0.0;
		$currency = method_exists( $order, 'get_currency' ) ? (string) $order->get_currency() : '';
		$customer_id = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;
		$billing_email = method_exists( $order, 'get_billing_email' ) ? (string) $order->get_billing_email() : '';

		return [
			'id'            => $order_id,
			'status'        => $status,
			'total'         => $total,
			'currency'      => $currency,
			'customer_id'   => $customer_id,
			'billing_email' => $billing_email,
		];
	}

	private static function resolve_order_products_payload( $order ): array {
		$line_items = [];

		if ( ! method_exists( $order, 'get_items' ) ) {
			return [ 'line_items' => $line_items ];
		}

		$items = $order->get_items();
		if ( ! is_iterable( $items ) ) {
			return [ 'line_items' => $line_items ];
		}

		foreach ( $items as $item ) {
			$product_id = is_object( $item ) && method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : (int) ( is_array( $item ) ? ( $item['product_id'] ?? 0 ) : 0 );
			$variation_id = is_object( $item ) && method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : (int) ( is_array( $item ) ? ( $item['variation_id'] ?? 0 ) : 0 );
			$product_name = is_object( $item ) && method_exists( $item, 'get_name' ) ? (string) $item->get_name() : (string) ( is_array( $item ) ? ( $item['product_name'] ?? '' ) : '' );
			$quantity = is_object( $item ) && method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : (int) ( is_array( $item ) ? ( $item['quantity'] ?? 0 ) : 0 );
			$subtotal = is_object( $item ) && method_exists( $item, 'get_subtotal' ) ? (float) $item->get_subtotal() : (float) ( is_array( $item ) ? ( $item['subtotal'] ?? 0 ) : 0 );
			$total = is_object( $item ) && method_exists( $item, 'get_total' ) ? (float) $item->get_total() : (float) ( is_array( $item ) ? ( $item['total'] ?? 0 ) : 0 );
			$subtotal_tax = is_object( $item ) && method_exists( $item, 'get_subtotal_tax' ) ? (float) $item->get_subtotal_tax() : (float) ( is_array( $item ) ? ( $item['subtotal_tax'] ?? 0 ) : 0 );
			$tax_class = is_object( $item ) && method_exists( $item, 'get_tax_class' ) ? (string) $item->get_tax_class() : (string) ( is_array( $item ) ? ( $item['tax_class'] ?? '' ) : '' );
			$tax_status = is_object( $item ) && method_exists( $item, 'get_tax_status' ) ? (string) $item->get_tax_status() : (string) ( is_array( $item ) ? ( $item['tax_status'] ?? '' ) : '' );

			$line_items[] = [
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'product_name' => $product_name,
				'quantity'     => $quantity,
				'subtotal'     => $subtotal,
				'total'        => $total,
				'subtotal_tax' => $subtotal_tax,
				'tax_class'    => $tax_class,
				'tax_status'   => $tax_status,
			];
		}

		return [
			'line_items' => $line_items,
		];
	}

	private static function resolve_order_meta_data( int $order_id, $order ): array {
		$meta_data = [];

		if ( method_exists( $order, 'get_meta_data' ) ) {
			$items = $order->get_meta_data();
			if ( is_iterable( $items ) ) {
				foreach ( $items as $item ) {
					$data = null;
					if ( is_object( $item ) && method_exists( $item, 'get_data' ) ) {
						$data = $item->get_data();
					} elseif ( is_array( $item ) ) {
						$data = $item;
					}

					if ( ! is_array( $data ) ) {
						continue;
					}

					$key = (string) ( $data['key'] ?? '' );
					if ( '' === $key ) {
						continue;
					}

					$meta_data[ ltrim( $key, '_' ) ] = $data['value'] ?? null;
				}
			}
		}

		if ( empty( $meta_data ) && function_exists( 'get_post_meta' ) && $order_id > 0 ) {
			$raw_meta = get_post_meta( $order_id );
			if ( is_array( $raw_meta ) ) {
				foreach ( $raw_meta as $key => $value ) {
					if ( ! is_string( $key ) || '' === $key ) {
						continue;
					}
					$meta_data[ ltrim( $key, '_' ) ] = is_array( $value ) ? ( $value[0] ?? null ) : $value;
				}
			}
		}

		if ( method_exists( $order, 'get_meta' ) ) {
			$checkout_id = $order->get_meta( '_wcf_checkout_id', true );
			if ( ! empty( $checkout_id ) && empty( $meta_data['wcf_checkout_id'] ) ) {
				$meta_data['wcf_checkout_id'] = $checkout_id;
			}
		}

		return $meta_data;
	}

	private static function matches_selected_step( array $config, int $step_id, array $keys = [ 'step_id' ] ): bool {
		$selected_step_id = self::resolve_selected_step_id( $config, $keys );
		if ( null === $selected_step_id ) {
			return true;
		}

		return $step_id > 0 && $selected_step_id === $step_id;
	}

	private static function resolve_selected_step_id( array $config, array $keys ): ?int {
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

	private static function resolve_flow_payload( int $flow_id ): ?array {
		$flow_post = get_post( $flow_id );
		$step_refs = self::get_flow_steps( $flow_id );
		if ( ! is_object( $flow_post ) && empty( $step_refs ) ) {
			return null;
		}

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
			'flow_id'      => $flow_id,
			'flow_title'   => (string) ( $flow_post->post_title ?? '' ),
			'flow_status'  => (string) ( $flow_post->post_status ?? '' ),
			'flow_url'     => get_permalink( $flow_id ),
			'total_steps'  => count( $steps ),
			'steps'        => $steps,
			'created_at'   => (string) ( $flow_post->post_date ?? '' ),
			'updated_at'   => (string) ( $flow_post->post_modified ?? '' ),
		];
	}

	private static function resolve_step_payload( int $step_id ): ?array {
		$step_post = get_post( $step_id );

		$flow_id      = 0;
		$step_type    = '';
		$next_step_id = 0;

		if ( function_exists( '\wcf_get_step' ) ) {
			$step_obj = wcf_get_step( $step_id );
			if ( is_object( $step_obj ) ) {
				if ( method_exists( $step_obj, 'get_flow_id' ) ) {
					$flow_id = (int) $step_obj->get_flow_id();
				}
				if ( method_exists( $step_obj, 'get_step_type' ) ) {
					$step_type = (string) $step_obj->get_step_type();
				}
				if ( method_exists( $step_obj, 'get_direct_next_step_id' ) ) {
					$next_step_id = (int) $step_obj->get_direct_next_step_id();
				}
			}
		}

		if ( $flow_id <= 0 ) {
			$utils = self::get_cartflows_utils();
			if ( $utils && method_exists( $utils, 'get_flow_id_from_step_id' ) ) {
				$flow_id = (int) $utils->get_flow_id_from_step_id( $step_id );
			}
		}

		if ( '' === $step_type && function_exists( '\wcf_get_step_type' ) ) {
			$step_type = (string) wcf_get_step_type( $step_id );
		}

		if ( $next_step_id <= 0 ) {
			$utils = self::get_cartflows_utils();
			if ( $utils && method_exists( $utils, 'get_next_step_id' ) ) {
				$next_step_id = (int) $utils->get_next_step_id( $flow_id, $step_id );
			}
		}

		if ( $flow_id <= 0 && '' === $step_type && $next_step_id <= 0 ) {
			return null;
		}

		return [
			'step_id'       => $step_id,
			'flow_id'       => $flow_id,
			'step_type'     => $step_type,
			'step_title'    => is_object( $step_post ) ? (string) ( $step_post->post_title ?? '' ) : '',
			'step_status'   => is_object( $step_post ) ? (string) ( $step_post->post_status ?? '' ) : '',
			'step_url'      => get_permalink( $step_id ),
			'next_step_id'  => $next_step_id,
			'created_at'    => is_object( $step_post ) ? (string) ( $step_post->post_date ?? '' ) : '',
			'updated_at'    => is_object( $step_post ) ? (string) ( $step_post->post_modified ?? '' ) : '',
		];
	}

	private static function get_flow_steps( int $flow_id ): array {
		$utils = self::get_cartflows_utils();
		if ( ! $utils || ! method_exists( $utils, 'get_flow_steps' ) ) {
			return [];
		}

		$steps = $utils->get_flow_steps( $flow_id );
		return is_array( $steps ) ? $steps : [];
	}

	private static function get_cartflows_utils() {
		if ( ! function_exists( '\wcf' ) ) {
			return null;
		}

		$wcf = wcf();
		if ( ! is_object( $wcf ) ) {
			return null;
		}

		$utils = $wcf->utils ?? null;
		return is_object( $utils ) ? $utils : null;
	}

	private static function is_cartflows_available(): bool {
		return class_exists( '\Cartflows_Loader' ) || function_exists( '\wcf' ) || function_exists( '\wcf_get_step' );
	}

	private static function main_response( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	private static function error_response( string $message, array $input = [] ): array {
		return self::main_response(
			array_merge(
				$input,
				[
					'error' => $message,
				]
			)
		);
	}
}
