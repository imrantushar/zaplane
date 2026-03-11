<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Essentialblocks extends IntegrationBase {


	public static function get_slug(): string {
		return 'essentialblocks';
	}

	public static function get_triggers(): array {
		return [
			'eb_form_submit_before_email' => [
				'label' => 'Form Submission',
				'hook' => 'eb_form_submit_before_email'
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'eb_form_submit_before_email':
					$form_name = $args[0] ?? '';
					$form_data = $args[1] ?? [];
					$result = [
						'form_name' => $form_name
					];

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
