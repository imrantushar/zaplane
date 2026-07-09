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
		// Routes are dynamic; the canvas derives the visible paths from config.
		// This is a generous superset used only for validation / the manifest.
		return [ 'path_1', 'path_2', 'path_3', 'path_4', 'path_5', 'path_6', 'path_7', 'path_8', 'fallback' ];
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

		return [
			[
				'key'         => 'value',
				'label'       => 'Value to route on',
				'type'        => 'expression',
				'required'    => true,
				'placeholder' => '{{1.type}}',
			],
			[
				'key'    => 'routes',
				'label'  => 'Routes',
				'type'   => 'repeater',
				'help'   => 'Add a route for each path. They are checked top to bottom — the run takes the first match, otherwise the Fallback path. Each route is a branch on the canvas; wire a node to it with the “+”.',
				'fields' => [
					[
						'key'     => 'operator',
						'label'   => 'Operator',
						'type'    => 'select',
						'default' => '==',
						'options' => self::operator_options(),
					],
					[
						'key'         => 'value',
						'label'       => 'Compare against',
						'type'        => 'expression',
						'required'    => false,
						'placeholder' => 'e.g. paid',
					],
				],
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];
		// Compare as strings so a typed value (e.g. an integer from a single-token
		// expression) still matches a text case; numeric strings still compare
		// numerically for </>, and ==/!= coerce numerics inside Condition::compare.
		$value  = (string) ( $config['value'] ?? '' );

		$port = 'fallback';
		$i    = 0;
		foreach ( self::normalize_routes( $config ) as $route ) {
			$i++;
			$case = (string) ( $route['value'] ?? '' );
			$op   = $route['operator'] ?? '==';

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

	/**
	 * Return the configured routes as a list of [operator, value].
	 *
	 * @param array<string,mixed> $config
	 * @return array<int,array{operator:string,value:string}>
	 */
	private static function normalize_routes( array $config ): array {
		$routes = [];
		foreach ( (array) ( $config['routes'] ?? [] ) as $r ) {
			if ( is_array( $r ) ) {
				$routes[] = [
					'operator' => $r['operator'] ?? '==',
					'value'    => (string) ( $r['value'] ?? '' ),
				];
			}
		}
		return $routes;
	}
}
