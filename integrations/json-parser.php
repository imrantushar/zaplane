<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JSON Parser tool — decode a JSON string into data, or encode data as JSON.
 */
class JsonParser extends IntegrationBase {

	public static function get_slug(): string {
		return 'json_parser';
	}

	public static function get_name(): string {
		return 'JSON Parser';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_icon(): string {
		return 'json.svg';
	}

	public static function get_actions(): array {
		return [
			'parse'     => [ 'label' => 'Parse JSON' ],
			'stringify' => [ 'label' => 'Stringify JSON' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		if ( 'stringify' === $action ) {
			return [
				[
					'key' => 'data',
					'label' => 'Data',
					'type' => 'textarea',
					'required' => true
				],
				[
					'key' => 'pretty',
					'label' => 'Pretty-print',
					'type' => 'checkbox',
					'default' => false
				],
			];
		}

		return [
			[
				'key' => 'json',
				'label' => 'JSON text',
				'type' => 'textarea',
				'required' => true
			],
		];
	}

	public static function get_action_sample_output( string $action ): array {
		if ( 'stringify' === $action ) {
			return [ 'json' => '{"key":"value"}' ];
		}
		return [
			'data' => [ 'key' => 'value' ],
			'success' => true,
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$action = $node['data']['event'] ?? 'parse';
		$config = $node['data']['config'] ?? [];

		if ( 'stringify' === $action ) {
			$value = $config['data'] ?? null;
			if ( is_string( $value ) ) {
				$decoded = json_decode( $value, true );
				if ( JSON_ERROR_NONE === json_last_error() ) {
					$value = $decoded;
				}
			}
			$flags = empty( $config['pretty'] ) ? 0 : JSON_PRETTY_PRINT;
			return [
				'port' => 'main',
				'data' => [ 'json' => wp_json_encode( $value, $flags ) ],
			];
		}

		$decoded = json_decode( (string) ( $config['json'] ?? '' ), true );
		$ok      = JSON_ERROR_NONE === json_last_error();

		$data = [
			'success' => $ok,
			'data'    => $ok ? $decoded : null,
		];

		if ( ! $ok ) {
			$data['error'] = json_last_error_msg();
		}

		return [
			'port' => 'main',
			'data' => $data,
		];
	}
}
