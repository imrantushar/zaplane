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
			[ 'key' => 'case_1', 'label' => 'Path 1 equals', 'type' => 'expression', 'required' => false ],
			[ 'key' => 'case_2', 'label' => 'Path 2 equals', 'type' => 'expression', 'required' => false ],
			[ 'key' => 'case_3', 'label' => 'Path 3 equals', 'type' => 'expression', 'required' => false ],
			[ 'key' => 'case_4', 'label' => 'Path 4 equals', 'type' => 'expression', 'required' => false ],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];
		$value  = (string) ( $config['value'] ?? '' );

		$port = 'fallback';
		for ( $i = 1; $i <= 4; $i++ ) {
			$case = $config[ 'case_' . $i ] ?? '';
			if ( '' !== (string) $case && (string) $case === $value ) {
				$port = 'path_' . $i;
				break;
			}
		}

		return [
			'port' => $port,
			'data' => array_merge( $input, [ 'matched_path' => $port, 'routed_value' => $value ] ),
		];
	}
}
