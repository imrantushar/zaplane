<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

/**
 * Router — switch on a value and send the run down one of several labelled
 * paths (Make's Router / n8n's Switch). Compares an input value against up to
 * four cases; the first match routes to its path, otherwise the Fallback path.
 *
 * Connect downstream nodes to the matching output handle (path_1..path_4 or
 * fallback).
 */
class Router extends IntegrationBase {

	public static function get_slug(): string {
		return 'router';
	}

	public static function get_name(): string {
		return 'Router';
	}

	public static function get_icon(): string {
		return 'router.svg';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_triggers(): array {
		return [];
	}

	public static function requires_connection(): bool {
		return false;
	}

	public static function get_output_ports(): array {
		return [ 'path_1', 'path_2', 'path_3', 'path_4', 'fallback' ];
	}

	public static function get_actions(): array {
		return [
			'route' => [ 'label' => 'Route By Value' ],
		];
	}

	private static function operator_options(): array {
		return [
			[ 'label' => 'Equals', 'value' => '==' ],
			[ 'label' => 'Not Equals', 'value' => '!=' ],
			[ 'label' => 'Equals (ignore case)', 'value' => 'equals_ci' ],
			[ 'label' => 'Contains', 'value' => 'contains' ],
			[ 'label' => 'Contains (ignore case)', 'value' => 'contains_ci' ],
			[ 'label' => 'Not Contains', 'value' => 'not_contains' ],
			[ 'label' => 'Starts With', 'value' => 'starts_with' ],
			[ 'label' => 'Ends With', 'value' => 'ends_with' ],
			[ 'label' => 'Greater Than', 'value' => '>' ],
			[ 'label' => 'Less Than', 'value' => '<' ],
			[ 'label' => 'Greater or Equal', 'value' => '>=' ],
			[ 'label' => 'Less or Equal', 'value' => '<=' ],
			[ 'label' => 'Matches Regex', 'value' => 'matches_regex' ],
			[ 'label' => 'In List', 'value' => 'in_list' ],
			[ 'label' => 'Between', 'value' => 'between' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		if ( 'route' !== $action ) {
			return [];
		}

		$fields = [
			[
				'key'         => 'value',
				'label'       => 'Value to route on',
				'type'        => 'expression',
				'required'    => true,
				'placeholder' => '{{1.type}}',
				'help'        => 'Each route below is checked in order; the run takes the first match, else the Fallback branch. Wire a node to each branch handle (Path 1–4 / Fallback).',
			],
		];

		for ( $i = 1; $i <= 4; $i++ ) {
			$fields[] = [
				'key'     => 'op_' . $i,
				'label'   => "Path {$i} — operator",
				'type'    => 'select',
				'default' => '==',
				'options' => self::operator_options(),
			];
			$fields[] = [
				'key'         => 'case_' . $i,
				'label'       => "Path {$i} — value",
				'type'        => 'expression',
				'required'    => false,
				'placeholder' => 'Leave blank to skip this route',
			];
		}

		return $fields;
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];
		// Compare as strings so a typed value (e.g. an integer from a single-token
		// expression) still matches a text case; numeric strings still compare
		// numerically for </>, and ==/!= coerce numerics inside Condition::compare.
		$value  = (string) ( $config['value'] ?? '' );

		$port = 'fallback';
		for ( $i = 1; $i <= 4; $i++ ) {
			$case = (string) ( $config[ 'case_' . $i ] ?? '' );
			$op   = $config[ 'op_' . $i ] ?? '==';

			// An empty case skips the route (unless the operator is an emptiness check).
			if ( '' === $case && ! in_array( $op, [ 'is_empty', 'is_not_empty', 'is_true', 'is_false' ], true ) ) {
				continue;
			}

			if ( Condition::compare( $value, $case, $op ) ) {
				$port = 'path_' . $i;
				break;
			}
		}

		return [
			'port' => $port,
			'data' => array_merge( $input, [
				'matched_path' => $port,
				'routed_value' => $value,
			] ),
		];
	}
}
