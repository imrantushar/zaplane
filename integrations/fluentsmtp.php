<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Fluentsmtp extends IntegrationBase {


	public static function get_slug(): string {
		return 'fluentsmtp';
	}

	public static function get_name(): string {
		return 'FluentSMTP';
	}

	public static function get_icon(): string {
		return 'fluentsmtp.svg';
	}

	public static function get_triggers(): array {
		return [
			'email_sent_success' => [
				'label' => 'Email was sent successfully',
				'hook'  => 'wp_mail_succeeded',
			],
			'email_sent_failed' => [
				'label' => 'Failed to send email',
				'hook'  => 'fluentmail_email_sending_failed',
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'email_sent_success':
				$email_data = $args[0] ?? [];

				return [
					'success' => true,
					'data'    => $email_data,
				];

			case 'email_sent_failed':
				$log_id  = $args[1] ?? false;
				$handler = $args[2] ?? null;
				$data    = $args[3] ?? [];

				return [
					'success' => false,
					'log_id'  => $log_id,
					'handler' => is_object( $handler ) ? get_class( $handler ) : $handler,
					'data'    => $data,
					'error'   => $data['response']['message'] ?? 'Unknown error',
				];
		}//end switch

		return false;
	}
}
