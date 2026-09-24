<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Spectra extends IntegrationBase {


	public static function get_slug(): string {
		return 'spectra';
	}

	public static function get_name(): string {
		return 'Spectra Legacy';
	}
	public static function get_icon(): string {
		return 'spectra.svg';
	}
	/** @inheritDoc */
	public static function get_docs_url(): array {
		return [
			'trigger' => 'https://zaplane.app/docs/spectra-legacy/',
			'action'  => 'https://zaplane.app/docs/spectra-legacy/',
		];
	}
	public static function get_triggers(): array {
		return [
			'uagb_form_success' => [
				'label' => 'Form Submission',
				'hook' => 'uagb_form_success'
			],

		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'uagb_form_success':
				$form_fields = $args[0] ?? [];
				return [
					'form_id' => $form_fields['id'] ?? '',
					'form_fname' => $form_fields['First Name'] ?? '',
					'form_lname' => $form_fields['Last Name'] ?? '',
					'form_email' => $form_fields['Email'] ?? '',
					'form_message' => $form_fields['Message'] ?? '',
					'submitted_time' => current_time( 'mysql' ),
				];
		}

		return false;
	}
}
