<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

/**
 * Repeater — a numeric loop (repeat N times, or a from→to range). Unlike the
 * Iterator (which loops a list), this generates the sequence and exposes {{ i }}
 * per pass. Uses the same iterate/resume mechanism as the Iterator.
 */
class Repeater extends IntegrationBase {

	private const MAX_ITEMS = 10000;

	public static function get_slug(): string {
		return 'repeater';
	}

	public static function get_name(): string {
		return 'Repeater';
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
			'repeat' => [ 'label' => 'Repeat / Loop' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		return [
			[
				'key'     => 'mode',
				'label'   => 'Mode',
				'type'    => 'select',
				'default' => 'count',
				'options' => [
					[ 'value' => 'count', 'label' => 'Repeat N times' ],
					[ 'value' => 'range', 'label' => 'Number range (from → to)' ],
				],
			],
			[
				'key'        => 'times',
				'label'      => 'Times',
				'type'       => 'number',
				'default'    => 3,
				'depends_on' => [ 'mode' => 'count' ],
				'help'       => 'Runs the branch this many times. Read the counter downstream with {{ i }} (also {{ index }}, {{ is_first }}, {{ is_last }}, {{ total }}).',
			],
			[ 'key' => 'from', 'label' => 'From', 'type' => 'number', 'default' => 1, 'depends_on' => [ 'mode' => 'range' ] ],
			[ 'key' => 'to',   'label' => 'To',   'type' => 'number', 'default' => 10, 'depends_on' => [ 'mode' => 'range' ] ],
			[ 'key' => 'step', 'label' => 'Step', 'type' => 'number', 'default' => 1, 'depends_on' => [ 'mode' => 'range' ] ],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];

		if ( ! empty( $input['_is_iterating'] ) ) {
			$items = $input['_remaining'] ?? [];
			$index = (int) ( $input['_iter_index'] ?? 0 );
			$total = (int) ( $input['_iter_total'] ?? ( count( $items ) + $index ) );
		} else {
			$items = self::build_sequence( $config );
			$index = 0;
			$total = count( $items );
		}

		if ( empty( $items ) ) {
			return [
				'port' => 'done',
				'data' => [ 'total' => $total ],
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
				'i'        => $current,
				'index'    => $index,
				'is_first' => 0 === $index,
				'is_last'  => empty( $items ),
				'total'    => $total,
			],
		];
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<int,int>
	 */
	protected static function build_sequence( array $config ): array {
		if ( 'range' === ( $config['mode'] ?? 'count' ) ) {
			$from = (int) ( $config['from'] ?? 1 );
			$to   = (int) ( $config['to'] ?? 0 );
			$step = abs( (int) ( $config['step'] ?? 1 ) );
			if ( 0 === $step ) {
				$step = 1;
			}

			$out = [];
			if ( $from <= $to ) {
				for ( $i = $from; $i <= $to; $i += $step ) {
					$out[] = $i;
				}
			} else {
				for ( $i = $from; $i >= $to; $i -= $step ) {
					$out[] = $i;
				}
			}
			return array_slice( $out, 0, self::MAX_ITEMS );
		}

		$times = min( self::MAX_ITEMS, max( 0, (int) ( $config['times'] ?? 0 ) ) );
		return $times > 0 ? range( 1, $times ) : [];
	}
}
