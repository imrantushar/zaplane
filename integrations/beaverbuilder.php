<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Beaverbuilder extends IntegrationBase {


	public static function get_slug(): string {
		return 'beaverbuilder';
	}

	public static function get_name(): string {
		return 'Beaver Builder';
	}

	public static function get_icon(): string {
		return 'beaverbuilder.svg';
	}

	public static function get_triggers(): array {
		return [
			'contact_form_submission' => [
				'label' => 'Contact Form Submission',
				'hook' => 'fl_module_contact_form_after_send'
			],
			'login_form_submission' => [
				'label' => 'Login Form Submission',
				'hook' => 'fl_builder_login_form_submission_complete'
			],
			'subscribe_form_submission' => [
				'label' => 'Subscribe Form Submission',
				'hook' => 'fl_builder_subscribe_form_submission_complete'
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'contact_form_submission':
				$message = $args[2] ?? '';

				preg_match( '/Name: (.+)/', $message, $name_match );
				preg_match( '/Email: (.+)/', $message, $email_match );
				preg_match( '/Message:\s*(.+)/s', $message, $message_match );
				return [
					'name' => trim( $name_match[1] ?? '' ),
					'email' => trim( $email_match[1] ?? '' ),
					'message' => trim( $message_match[1] ?? '' ),
				];

			case 'login_form_submission':
				return [
					'user_pass' => $args[1] ?? '',
					'username' => $args[2] ?? '',
				];

			case 'subscribe_form_submission':
				return [
					'email' => $args[2] ?? '',
					'name' => $args[3] ?? '',
				];
		}//end switch

		return false;
	}
}
