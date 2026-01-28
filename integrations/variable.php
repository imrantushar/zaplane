<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\Expression;

class Variable extends IntegrationBase {

	/* ---------------------------------------------------------
	 * Identity
	 * --------------------------------------------------------- */

	public static function get_slug(): string { return 'variable'; }
	public static function get_name(): string { return 'Set Variable'; }
	public static function get_icon(): string { return 'variable'; }
	public static function get_category(): string { return 'tool'; }

	/* ---------------------------------------------------------
	 * Actions
	 * --------------------------------------------------------- */

	public static function get_actions(): array {
		return [
			'set' => [ 'label' => 'Set Variable' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		return [
			[ 'key' => 'name',  'label' => 'Variable Name', 'type' => 'text', 'required' => true ],
			[ 'key' => 'value', 'label' => 'Value',         'type' => 'expression' ],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];
		$name   = $config['name'] ?? '';
		$value  = Expression::evaluate( $config['value'] ?? '', $input );

		if ( empty( $name ) ) {
			throw new \Exception( 'Variable name is required' );
		}

		$input[ $name ] = $value;

		return [ 'port' => 'main', 'data' => $input ];
	}
}
