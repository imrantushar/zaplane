<?php
namespace Zaplane\Integrations\Fluentcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait NodeHelperTrait {
	private static function resolve_node_event( array $node, string $type ): string {
		$candidates = [
			$node['event'] ?? null,
			$node['data']['event'] ?? null,
			$node[ $type ] ?? null,
			$node['data'][ $type ] ?? null,
			$node['config']['event'] ?? null,
			$node['config'][ $type ] ?? null,
		];

		foreach ( $candidates as $candidate ) {
			if ( ! is_scalar( $candidate ) ) {
				continue;
			}

			$event = trim( (string) $candidate );
			if ( '' !== $event ) {
				return $event;
			}
		}

		return '';
	}

	private static function resolve_node_config( array $node ): array {
		if ( isset( $node['data']['config'] ) && is_array( $node['data']['config'] ) ) {
			return $node['data']['config'];
		}

		if ( isset( $node['config']['data'] ) && is_array( $node['config']['data'] ) ) {
			return $node['config']['data'];
		}

		if ( isset( $node['config']['config'] ) && is_array( $node['config']['config'] ) ) {
			return $node['config']['config'];
		}

		if ( isset( $node['config'] ) && is_array( $node['config'] ) ) {
			return self::strip_structural_node_keys( $node['config'] );
		}

		return self::strip_structural_node_keys( $node );
	}

	private static function strip_structural_node_keys( array $config ): array {
		unset( $config['app'], $config['event'], $config['action'], $config['trigger'], $config['type'], $config['hook'], $config['label'], $config['connection_id'], $config['data'] );
		return $config;
	}

	private static function resolve_entity_id_for_action( array $config, array $input, string $id_key, array $entity_keys ): int {
		$id = self::parse_positive_int( $config[ $id_key ] ?? 0 );
		if ( $id > 0 ) {
			return $id;
		}

		$id = self::parse_positive_int( $input[ $id_key ] ?? 0 );
		if ( $id > 0 ) {
			return $id;
		}

		foreach ( $entity_keys as $entity_key ) {
			$id = self::parse_positive_int( $input[ $entity_key ] ?? null );
			if ( $id > 0 ) {
				return $id;
			}
		}

		return 0;
	}

	private static function normalize_order_value( $value ): array {
		$payload = self::normalize_payload_value( $value );
		if ( ! is_array( $payload ) ) {
			$payload = [];
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'get_id' ) ) {
				$payload['id'] = self::parse_positive_int( $value->get_id() );
			}
			if ( method_exists( $value, 'get_status' ) ) {
				$payload['status'] = (string) $value->get_status();
			}
			if ( method_exists( $value, 'get_total' ) ) {
				$payload['total_amount'] = (float) $value->get_total();
			}
			if ( method_exists( $value, 'get_currency' ) ) {
				$payload['currency'] = (string) $value->get_currency();
			}
			if ( method_exists( $value, 'get_customer_id' ) ) {
				$payload['customer_id'] = (int) $value->get_customer_id();
			}
		}

		$order_id = self::parse_positive_int( $payload['id'] ?? ( $payload['order_id'] ?? 0 ) );
		if ( $order_id > 0 ) {
			$payload['id']       = $order_id;
			$payload['order_id'] = $order_id;
		}

		return $payload;
	}

	private static function normalize_payload_value( $value ) {
		if ( null === $value || is_scalar( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::normalize_payload_value( $item );
			}

			return $value;
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'toArray' ) ) {
				return self::normalize_payload_value( $value->toArray() );
			}

			if ( method_exists( $value, 'get_id' ) ) {
				return [
					'id' => self::parse_positive_int( $value->get_id() ),
				];
			}
		}

		return [];
	}

	private static function matches_id_filter( array $config, string $config_key, int $actual_id ): bool {
		if ( ! array_key_exists( $config_key, $config ) ) {
			return true;
		}

		$selected = $config[ $config_key ];
		if ( self::is_any_selection( $selected ) ) {
			return true;
		}

		$selected_id = self::parse_positive_int( $selected );
		if ( $selected_id <= 0 ) {
			return true;
		}

		if ( $actual_id <= 0 ) {
			return false;
		}

		return $selected_id === $actual_id;
	}

	private static function is_any_selection( $value ): bool {
		if ( null === $value || false === $value ) {
			return true;
		}

		if ( is_string( $value ) ) {
			$normalized = sanitize_key( trim( $value ) );
			return '' === $normalized || 'any' === $normalized;
		}

		if ( is_array( $value ) ) {
			foreach ( [ 'value', 'name', 'id' ] as $key ) {
				if ( array_key_exists( $key, $value ) && self::is_any_selection( $value[ $key ] ) ) {
					return true;
				}
			}

			return false;
		}

		if ( is_object( $value ) ) {
			foreach ( [ 'value', 'name', 'id' ] as $key ) {
				if ( isset( $value->{$key} ) && self::is_any_selection( $value->{$key} ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function sanitize_status( $value ): string {
		$status = sanitize_key( (string) $value );
		return 'any' === $status ? '' : $status;
	}

	private static function build_select_options( array $values, array $fallback_values, string $any_label ): array {
		$options  = [
			[
				'name'  => 'any',
				'label' => $any_label,
			],
		];
		$prepared = [];

		foreach ( $values as $value ) {
			$status = sanitize_key( (string) $value );
			if ( '' === $status || 'any' === $status ) {
				continue;
			}
			$prepared[ $status ] = true;
		}

		foreach ( $fallback_values as $value ) {
			$status = sanitize_key( (string) $value );
			if ( '' === $status || isset( $prepared[ $status ] ) ) {
				continue;
			}
			$prepared[ $status ] = true;
		}

		foreach ( array_keys( $prepared ) as $status ) {
			$options[] = [
				'name'  => $status,
				'label' => ucfirst( str_replace( '_', ' ', $status ) ),
			];
		}

		return $options;
	}

	private static function is_duplicate_order_event( string $event, int $order_id, int $customer_id, array $payload ): bool {
		if ( 'order_canceled' !== $event ) {
			return false;
		}

		static $seen = [];

		$signature = [
			'event'       => $event,
			'order_id'    => $order_id,
			'customer_id' => $customer_id,
			'reason'      => (string) ( $payload['reason'] ?? '' ),
			'old_status'  => (string) ( $payload['old_status'] ?? '' ),
			'new_status'  => (string) ( $payload['new_status'] ?? '' ),
		];

		$key = md5( wp_json_encode( $signature ) ?: '' );
		if ( isset( $seen[ $key ] ) ) {
			return true;
		}

		$seen[ $key ] = true;
		return false;
	}

	private static function parse_positive_int( $value ): int {
		if ( is_int( $value ) || is_float( $value ) ) {
			$parsed = (int) $value;
			return $parsed > 0 ? $parsed : 0;
		}

		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( '' === $value || 'any' === strtolower( $value ) ) {
				return 0;
			}

			if ( is_numeric( $value ) ) {
				$parsed = (int) $value;
				return $parsed > 0 ? $parsed : 0;
			}

			if ( preg_match( '/\d+/', $value, $matches ) ) {
				$parsed = isset( $matches[0] ) ? (int) $matches[0] : 0;
				return $parsed > 0 ? $parsed : 0;
			}

			return 0;
		}

		if ( is_array( $value ) ) {
			foreach ( [ 'id', 'ID', 'value', 'name', 'order_id', 'customer_id', 'subscription_id', 'product_id', 'user_id' ] as $key ) {
				if ( array_key_exists( $key, $value ) ) {
					return self::parse_positive_int( $value[ $key ] );
				}
			}

			if ( isset( $value[0] ) ) {
				return self::parse_positive_int( $value[0] );
			}

			return 0;
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'get_id' ) ) {
				return self::parse_positive_int( $value->get_id() );
			}

			foreach ( [ 'ID', 'id', 'value', 'name', 'order_id', 'customer_id', 'subscription_id', 'product_id', 'user_id' ] as $key ) {
				if ( isset( $value->{$key} ) ) {
					return self::parse_positive_int( $value->{$key} );
				}
			}

			return 0;
		}

		$parsed = (int) $value;
		return $parsed > 0 ? $parsed : 0;
	}

	private static function main_response( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	private static function error_response( string $message, array $input = [] ): array {
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

	private static function relation_list_response( array $config, array $input, string $id_key, array $entity_keys, string $model_class, string $relation, string $result_key ): array {
		$entity_id = self::resolve_entity_id_for_action( $config, $input, $id_key, $entity_keys );
		if ( $entity_id <= 0 ) {
			return self::error_response( ucfirst( str_replace( '_', ' ', $id_key ) ) . ' is required', $input );
		}

		$model = self::find_model_by_id( $model_class, $entity_id );
		if ( ! $model ) {
			return self::error_response( 'Record not found', $input );
		}

		$items = self::normalize_payload_value( self::get_model_relation( $model, $relation ) );
		if ( ! is_array( $items ) ) {
			$items = [];
		}

		return self::main_response(
		array_merge( $input, [ $id_key => $entity_id, $result_key => $items ] )
		);
	}

	private static function relation_single_response( array $config, array $input, string $id_key, array $entity_keys, string $model_class, string $relation, string $result_key ): array {
		$entity_id = self::resolve_entity_id_for_action( $config, $input, $id_key, $entity_keys );
		if ( $entity_id <= 0 ) {
			return self::error_response( ucfirst( str_replace( '_', ' ', $id_key ) ) . ' is required', $input );
		}

		$model = self::find_model_by_id( $model_class, $entity_id );
		if ( ! $model ) {
			return self::error_response( 'Record not found', $input );
		}

		$value = self::normalize_payload_value( self::get_model_relation( $model, $relation ) );

		return self::main_response(
			array_merge( $input, [ $id_key => $entity_id, $result_key => $value ] )
		);
	}
}
