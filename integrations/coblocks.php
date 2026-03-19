<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Coblocks extends IntegrationBase {


	public static function get_slug(): string {
		return 'coblocks';
	}
	public static function get_icon(): string {
		return 'coblocks.svg';
	}
	public static function get_triggers(): array {
		return [
			'coblocks_form_submit' => [
				'label' => 'Form Submission',
				'hook' => 'coblocks_form_submit'
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'coblocks_form_submit':
					$form_data = $args[0] ?? [];
				if ( is_array( $form_data ) ) {
					foreach ( $form_data as $key => $value ) {
						$result[ $key ] = $value;
					}
				}
				return $result;
		}

		return false;
	}

	public static function execute_node( array $node, array $input ): array {
		return [
			'port' => 'main',
			'data' => $input
		];
	}
}
