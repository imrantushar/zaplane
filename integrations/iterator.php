<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Iterator extends IntegrationBase {

	/* ---------------------------------------------------------
	 * Identity
	 * --------------------------------------------------------- */

	public static function get_slug(): string { return 'iterator'; }
	public static function get_name(): string { return 'Iterator'; }
	public static function get_icon(): string { return 'iterator'; }
	public static function get_category(): string { return 'tool'; }

	/* ---------------------------------------------------------
	 * Actions
	 * --------------------------------------------------------- */

	public static function get_actions(): array {
		return [
			'loop' => [ 'label' => 'Loop Over Items' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		return [
			[ 'key' => 'source', 'label' => 'Source Array Key', 'type' => 'text', 'required' => true ],
		];
	}

	public static function get_output_ports(): array {
		return [ 'loop', 'done' ];
	}

	public static function execute_node( array $node, array $input ): array {
		$source = $node['config']['source'] ?? $node['data']['config']['source'] ?? '';
		$items  = $input[ $source ] ?? [];

		if ( empty( $items ) || ! is_array( $items ) ) {
			return [ 'port' => 'done', 'data' => $input ];
		}

		$current = array_shift( $items );

		return [
			'port' => 'loop',
			'data' => array_merge( $input, [
				'item'       => $current,
				'_remaining' => $items,
			] ),
		];
	}
}
