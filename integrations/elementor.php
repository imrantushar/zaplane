<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elementor extends IntegrationBase {


	public static function get_slug(): string {
		return 'elementor';
	}
	public static function get_icon(): string {
		return 'elementor';
	}
	public static function get_triggers(): array {
		return [
			'elementor_pro/forms/new_record' => [
				'label' => 'Form New Record',
				'hook' => 'elementor_pro/forms/new_record'
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'elementor_pro/forms/new_record':
				$form_data  = $args[0] ?? '';
				if ( ! $form_data ) {
					return [];
				}
				$data  = $form_data->get( 'sent_data' );
				$result  = [];
				foreach ( $data as $form_field => $value ) {
					$result[ $form_field ]  = $value;
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
