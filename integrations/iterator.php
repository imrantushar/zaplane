<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Iterator extends IntegrationBase {


	public static function get_slug(): string {
		return 'iterator';
	}

	public static function get_name(): string {
		return 'Iterator';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_icon(): string {
		return 'iterator';
	}

	public static function get_output_ports(): array {
		return [ 'loop', 'done' ];
	}

	public static function get_actions(): array {
		return [
			'iterator' => [ 'label' => 'Iterator / Loop' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		return [
			[
				'key'         => 'source',
				'label'       => 'List to loop over',
				'type'        => 'expression',
				'required'    => true,
				'placeholder' => 'Type @ to pick a list from an earlier step',
				'help'        => 'Pick an array/list from a previous step (e.g. parsed CSV rows or an API list). The workflow runs once per item — read the current entry downstream with {{ item }} (also available: {{ index }}, {{ is_first }}, {{ is_last }}, {{ total }}).',
			],
			[
				'key'         => 'offset',
				'label'       => 'Skip first N (offset)',
				'type'        => 'number',
				'required'    => false,
				'default'     => 0,
			],
			[
				'key'         => 'limit',
				'label'       => 'Max items (limit)',
				'type'        => 'number',
				'required'    => false,
				'placeholder' => 'blank = no limit',
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];

		// Support the iterator re-feeding itself.
		if ( ! empty( $input['_is_iterating'] ) ) {
			$items = $input['_remaining'] ?? [];
			$index = (int) ( $input['_iter_index'] ?? 0 );
			$total = (int) ( $input['_iter_total'] ?? ( count( $items ) + $index ) );
		} else {
			$items = self::resolve_items( $config['source'] ?? [], $input );

			$offset = (int) ( $config['offset'] ?? 0 );
			$limit  = ( isset( $config['limit'] ) && '' !== $config['limit'] ) ? (int) $config['limit'] : null;
			if ( $offset > 0 || null !== $limit ) {
				$items = array_values( array_slice( $items, max( 0, $offset ), $limit ) );
			}

			$index = 0;
			$total = count( $items );
		}

		if ( empty( $items ) ) {
			return [
				'port' => 'done',
				'data' => [ 'total' => $total ]
			];
		}

		$current = array_shift( $items );

		return [
			'port'           => 'loop',
			'status'         => 'iterate',
			'remaining'      => $items,
			'iterator_state' => [
				'_iter_index' => $index + 1,
				'_iter_total' => $total,
			],
			'data' => [
				'item'     => $current,
				'index'    => $index,
				'is_first' => 0 === $index,
				'is_last'  => empty( $items ),
				'total'    => $total,
			],
		];
	}

	/**
	 * Normalise the configured source into a plain array of items.
	 *
	 * With the expression picker the value usually resolves to an actual array.
	 * We also accept a JSON-encoded array string, and — for backward
	 * compatibility — a literal input key naming an array in the run context.
	 *
	 * @param mixed               $source
	 * @param array<string,mixed> $input
	 * @return array<int|string,mixed>
	 */
	protected static function resolve_items( $source, array $input ): array {
		if ( is_array( $source ) ) {
			return $source;
		}

		if ( is_string( $source ) ) {
			$trimmed = trim( $source );

			$decoded = json_decode( $trimmed, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}

			if ( '' !== $trimmed && isset( $input[ $trimmed ] ) && is_array( $input[ $trimmed ] ) ) {
				return $input[ $trimmed ];
			}
		}

		return [];
	}
}
