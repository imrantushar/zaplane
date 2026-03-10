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
				'key' => 'source',
				'label' => 'Source / Array Data',
				'type' => 'text',
				'required' => true
			]
		];
	}

	public static function execute_node( array $node, array $input ): array {

		// Support the iterator re-feeding itself
		if ( isset( $input['_is_iterating'] ) && $input['_is_iterating'] ) {
			$items = $input['_remaining'] ?? [];
		} else {
			// If 'source' was dynamically evaluated into an actual array, use it directly.
			// Otherwise fallback to searching input directly just in case it's a literal string context key.
			$sourceVal = $node['data']['config']['source'] ?? [];
			if ( is_array( $sourceVal ) ) {
				$items = $sourceVal;
			} elseif ( is_string( $sourceVal ) && isset( $input[ $sourceVal ] ) ) {
				$items = $input[ $sourceVal ];
			} else {
				$items = [];
			}
		}

		if ( empty( $items ) ) {
			return [
				'port' => 'done',
				'data' => [] 
			];
		}

		$current = array_shift( $items );

		return [
			'port' => 'loop',
			'status' => 'iterate',
			'remaining' => $items,
			'data' => [
				'item' => $current,
			],
		];
	}
}
