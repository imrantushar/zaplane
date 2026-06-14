<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Divi extends IntegrationBase {

	public static function get_slug(): string {
		return 'divi';
	}

	public static function get_name(): string {
		return 'Divi Builder';
	}

	public static function get_icon(): string {
		return 'divi.svg';
	}

	public static function get_triggers(): array {
		return [
			'contact_form_submit' => [
				'label' => 'Contact Form Submitted',
				'hook'  => 'et_pb_contact_form_submit',
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {

			case 'contact_form_submit':

				$form_fields = $args[0] ?? [];
				$form_meta   = $args[2] ?? [];

				$data = [
					'id'           => $form_meta['contact_form_unique_id'] ?? '',
					'post_id'      => $form_meta['post_id'] ?? '',
					'submitted_at' => current_time( 'mysql' ),
				];

				foreach ( $form_fields as $key => $field ) {

					if ( ! is_array( $field ) ) {
						continue;
					}

					$data[ $key ] = $field['value'] ?? '';
				}

				return $data;
		}

		return false;
	}
}
