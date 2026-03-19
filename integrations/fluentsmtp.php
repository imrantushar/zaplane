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
				'hook'  => ['fluentmail_email_sending_failed', 'fluentmail_email_sending_failed_no_fallback'],
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'email_sent_success':
                $to         = $args[0] ?? [];
                $subject    = $args[1] ?? '';
                $message    = $args[2] ?? '';
                $headers    = $args[3] ?? [];
                $attachments= $args[4] ?? [];

                return [
                    'success'     => true,
                    'to'          => $to,
                    'subject'     => $subject,
                    'message'     => $message,
                    'headers'     => $headers,
                    'attachments' => $attachments,
                ];

            case 'email_sent_failed':
                $email_data = $args[0] ?? [];
                $error_msg  = $args[1] ?? '';

                return [
                    'success'     => false,
                    'log_id'      => $email_data['log_id'] ?? false,
                    'to'          => $email_data['to'] ?? [],
                    'subject'     => $email_data['subject'] ?? '',
                    'message'     => $email_data['message'] ?? '',
                    'headers'     => $email_data['headers'] ?? [],
                    'attachments' => $email_data['attachments'] ?? [],
                    'error'       => $error_msg,
                ];
		}//end switch

		return false;
	}
}
