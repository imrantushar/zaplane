<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\Expression;

class Variable extends IntegrationBase {


	public static function get_slug(): string {
		return 'variable';
	}

	public static function get_name(): string {
		return 'Set Variable';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_icon(): string {
		return 'variable';
	}

	public static function get_actions(): array {
		return [
			'set' => [ 'label' => 'Set Variable' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		return [
			[
				'key' => 'name',
				'label' => 'Variable Name (e.g. my_custom_var)',
				'type' => 'text',
				'required' => true
			],
			[
				'key' => 'value',
				'label' => 'Value',
				'type' => 'expression'
			],
			[
				'key'     => 'type',
				'label'   => 'Cast as',
				'type'    => 'select',
				'default' => 'auto',
				'options' => [
					[
						'value' => 'auto',
						'label' => 'Auto (keep as-is)'
					],
					[
						'value' => 'string',
						'label' => 'Text'
					],
					[
						'value' => 'number',
						'label' => 'Number'
					],
					[
						'value' => 'boolean',
						'label' => 'Boolean'
					],
					[
						'value' => 'json',
						'label' => 'JSON (parse)'
					],
				],
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {

		$name  = $node['data']['config']['name'] ?? '';
		$value = $node['data']['config']['value'] ?? null;
		$type  = $node['data']['config']['type'] ?? 'auto';

		if ( ! empty( $name ) ) {
			$input[ $name ] = self::cast( $value, $type );
		}

		return [
			'port' => 'main',
			'data' => $input
		];
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private static function cast( $value, string $type ) {
		switch ( $type ) {
			case 'string':
				return is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
			case 'number':
				return is_numeric( $value ) ? ( 0 + $value ) : 0;
			case 'boolean':
				if ( is_bool( $value ) ) {
					return $value;
				}
				return in_array( strtolower( trim( (string) $value ) ), [ '1', 'true', 'yes', 'on' ], true );
			case 'json':
				if ( is_string( $value ) ) {
					$decoded = json_decode( $value, true );
					return null !== $decoded ? $decoded : $value;
				}
				return $value;
			case 'auto':
			default:
				return $value;
		}
	}
}
