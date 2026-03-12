<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Metform extends IntegrationBase {


	public static function get_slug(): string {
		return 'metform';
	}
	public static function get_icon(): string {
		return 'metform.svg';
	}
	public static function get_triggers(): array {
		return [
			'metform_after_store_form_data' => [
				'label' => 'Form Submitted',
				'hook' => 'metform_after_store_form_data'
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'metform_after_store_form_data':
				$form_data  = $args[1] ?? '';
				if ( ! $form_data ) {
					return [];
				}
				$result  = [];
				foreach ( $form_data as $form_field => $value ) {
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
