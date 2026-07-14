<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class FluentSmtp extends IntegrationBase {

	private const INTRODUCTION = 'Route WordPress email through SMTP and provider APIs with logging, fallback delivery, and delivery diagnostics powered by FluentSMTP.';
	private const CONTENT_TYPES = [ 'text/html', 'text/plain' ];

	public static function get_slug(): string {
		return 'fluentsmtp';
	}

	public static function get_name(): string {
		return 'FluentSMTP';
	}

	public static function get_icon(): string {
		return 'fluentsmtp.svg';
	}

	public static function get_introduction(): string {
		return self::INTRODUCTION;
	}

	public static function get_triggers(): array {
		return [
			'email_sent' => [
				'label' => 'Email Sent',
				'hook'  => 'wp_mail_succeeded',
			],
			'email_failed' => [
				'label' => 'Email Failed',
				'hook'  => 'wp_mail_failed',
			],
			'delivery_failed_no_fallback' => [
				'label' => 'Delivery Failed Without Fallback',
				'hook'  => 'fluentmail_email_sending_failed_no_fallback',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		return array_merge(
			[ self::get_recipient_filter_field() ],
			'delivery_failed_no_fallback' === $trigger
				? [ self::get_provider_filter_field() ]
				: []
		);
	}

	public static function get_actions(): array {
		return [
			'send_email' => [
				'label' => 'Send Email',
			],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		return 'send_email' === $action ? [
			[
				'key'         => 'to',
				'label'       => 'To',
				'type'        => 'email',
				'placeholder' => 'user@example.com, team@example.com',
				'required'    => true,
				'help'        => 'Use comma-separated email addresses or dynamic values.',
			],
			[
				'key'         => 'subject',
				'label'       => 'Subject',
				'type'        => 'text',
				'placeholder' => 'Your email subject',
				'required'    => true,
			],
			[
				'key'         => 'message',
				'label'       => 'Message',
				'type'        => 'textarea',
				'placeholder' => 'Write your email body here',
				'required'    => true,
			],
			[
				'key'     => 'content_type',
				'label'   => 'Content Type',
				'type'    => 'select',
				'options' => [
					[
						'label' => 'HTML',
						'value' => 'text/html',
					],
					[
						'label' => 'Plain Text',
						'value' => 'text/plain',
					],
				],
			],
			[
				'key'         => 'cc',
				'label'       => 'CC',
				'type'        => 'email',
				'placeholder' => 'manager@example.com, support@example.com',
			],
			[
				'key'         => 'bcc',
				'label'       => 'BCC',
				'type'        => 'email',
				'placeholder' => 'audit@example.com',
			],
			[
				'key'         => 'reply_to',
				'label'       => 'Reply-To',
				'type'        => 'email',
				'placeholder' => 'reply@example.com',
			],
			[
				'key'         => 'attachments',
				'label'       => 'Attachments',
				'type'        => 'textarea',
				'placeholder' => "/absolute/path/to/file.pdf\n/absolute/path/to/file-2.pdf",
				'help'        => 'Optional. One file path per line or comma-separated.',
			],
		] : [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$event  = self::get_node_event( $node );
		$config = self::get_node_config( $node );
		$resolver = [
			'email_sent'                   => 'resolve_email_sent_trigger',
			'email_failed'                 => 'resolve_email_failed_trigger',
			'delivery_failed_no_fallback'  => 'resolve_no_fallback_trigger',
		][ $event ] ?? '';

		if ( '' === $resolver ) {
			return false;
		}

		return self::{$resolver}( $args, $config );
	}

	public static function get_trigger_sample_output( string $event ): array {
		$recipient_emails = [ 'jane.doe@example.com' ];
		$subject          = 'Your receipt from Acme';
		$message          = '<p>Thanks for your order!</p>';
		$headers          = [ 'Content-Type: text/html; charset=UTF-8', 'From: Acme <no-reply@acme.example.com>' ];
		$attachments      = [ '/var/www/uploads/invoice-1042.pdf' ];

		$payload = [
			'recipient_emails' => $recipient_emails,
			'subject'          => $subject,
			'message'          => $message,
			'headers'          => $headers,
			'attachments'      => $attachments,
			'mail'             => [
				'to'          => $recipient_emails,
				'subject'     => $subject,
				'message'     => $message,
				'headers'     => $headers,
				'attachments' => $attachments,
			],
		];

		$samples = [
			'email_sent' => array_merge(
				[
					'success' => true,
					'status'  => 'sent',
				],
				$payload
			),
			'email_failed' => array_merge(
				[
					'success'       => true,
					'status'        => 'failed',
					'error_code'    => 'wp_mail_failed',
					'error_message' => 'The email could not be sent. SMTP connect() failed.',
				],
				$payload
			),
			'delivery_failed_no_fallback' => array_merge(
				[
					'success'  => true,
					'status'   => 'failed',
					'log_id'   => 4521,
					'provider' => 'smtp',
				],
				array_merge(
					$payload,
					[
						'response' => [
							'code'    => 535,
							'message' => 'Authentication failed',
						],
					]
				)
			),
		];

		if ( isset( $samples[ $event ] ) ) {
			return $samples[ $event ];
		}

		if ( false !== strpos( $event, 'fail' ) ) {
			return $samples['email_failed'];
		}

		return $samples['email_sent'];
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = self::get_node_event( $node );
		$config = self::get_node_config( $node );
		$method = 'action_' . $event;

		if ( method_exists( static::class, $method ) ) {
			return static::{$method}( $config, $input );
		}

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	private static function resolve_email_sent_trigger( array $args, array $config ) {
		$mail = $args[0] ?? [];
		return is_array( $mail )
			? self::build_mail_trigger_payload( self::normalize_mail_payload( $mail ), $config, 'sent' )
			: false;
	}

	private static function resolve_email_failed_trigger( array $args, array $config ) {
		$error = $args[0] ?? null;
		if ( ! is_object( $error ) || ! method_exists( $error, 'get_error_message' ) ) {
			return false;
		}

		$mail = method_exists( $error, 'get_error_data' ) ? $error->get_error_data() : [];
		$mail = is_array( $mail ) ? $mail : [];

		return self::build_mail_trigger_payload(
			self::normalize_mail_payload( $mail ),
			$config,
			'failed',
			[
				'error_code'    => method_exists( $error, 'get_error_code' ) ? (string) $error->get_error_code() : '',
				'error_message' => (string) $error->get_error_message(),
			]
		);
	}

	private static function resolve_no_fallback_trigger( array $args, array $config ) {
		$log_id = (int) ( $args[0] ?? 0 );
		$handler = $args[1] ?? null;
		$data = $args[2] ?? [];

		if ( ! is_array( $data ) ) {
			return false;
		}

		$payload  = self::normalize_logged_mail_payload( $data );
		$provider = self::resolve_failed_provider( $handler, $data );

		$required_provider = strtolower( trim( (string) ( $config['provider'] ?? '' ) ) );
		if ( '' !== $required_provider && strtolower( $provider ) !== $required_provider ) {
			return false;
		}

		return self::build_mail_trigger_payload(
			$payload,
			$config,
			'failed',
			[
				'log_id'   => $log_id,
				'provider' => $provider,
			]
		);
	}

	private static function action_send_email( array $config, array $input ): array {
		$to = self::parse_email_list( $config['to'] ?? '' );
		if ( empty( $to ) ) {
			return self::action_error( 'At least one valid recipient email is required', $input );
		}

		$subject = trim( (string) ( $config['subject'] ?? '' ) );
		if ( '' === $subject ) {
			return self::action_error( 'Email subject is required', $input );
		}

		$message = (string) ( $config['message'] ?? '' );
		if ( '' === trim( $message ) ) {
			return self::action_error( 'Email message is required', $input );
		}

		$content_type = self::normalize_content_type( $config['content_type'] ?? 'text/html' );
		$headers = array_merge(
			[ 'Content-Type: ' . $content_type . '; charset=UTF-8' ],
			self::build_email_headers(
				[
					'Cc'       => self::parse_email_list( $config['cc'] ?? '' ),
					'Bcc'      => self::parse_email_list( $config['bcc'] ?? '' ),
					'Reply-To' => self::parse_email_list( $config['reply_to'] ?? '' ),
				]
			)
		);

		$attachments = self::parse_string_list( $config['attachments'] ?? [] );
		$sent = wp_mail( $to, $subject, $message, $headers, $attachments );

		if ( ! $sent ) {
			return self::action_error( 'FluentSMTP could not send the email', $input );
		}

		return self::action_success(
			array_merge(
				$input,
				[
					'mail_sent'        => true,
					'fluentsmtp_action' => 'send_email',
					'recipient_emails' => $to,
					'subject'          => $subject,
					'message'          => $message,
					'headers'          => $headers,
					'attachments'      => $attachments,
					'content_type'     => $content_type,
				]
			)
		);
	}

	private static function normalize_mail_payload( array $mail ): array {
		$recipient_emails = self::extract_recipient_emails( $mail['to'] ?? [] );
		$headers = self::normalize_headers( $mail['headers'] ?? [] );
		$attachments = self::parse_string_list( $mail['attachments'] ?? [] );
		$subject = (string) ( $mail['subject'] ?? '' );
		$message = (string) ( $mail['message'] ?? '' );

		return [
			'recipient_emails' => $recipient_emails,
			'subject'          => $subject,
			'message'          => $message,
			'headers'          => $headers,
			'attachments'      => $attachments,
			'mail'             => [
				'to'          => $recipient_emails,
				'subject'     => $subject,
				'message'     => $message,
				'headers'     => $headers,
				'attachments' => $attachments,
			],
		];
	}

	private static function normalize_logged_mail_payload( array $mail ): array {
		$normalized = [
			'to' => maybe_unserialize( $mail['to'] ?? [] ),
			'subject' => $mail['subject'] ?? '',
			'body' => $mail['body'] ?? '',
			'headers' => maybe_unserialize( $mail['headers'] ?? [] ),
			'attachments' => maybe_unserialize( $mail['attachments'] ?? [] ),
		];

		$payload = self::normalize_mail_payload(
			[
				'to' => $normalized['to'],
				'subject' => $normalized['subject'],
				'message' => $normalized['body'],
				'headers' => $normalized['headers'],
				'attachments' => $normalized['attachments'],
			]
		);

		$payload['response'] = maybe_unserialize( $mail['response'] ?? [] );
		return $payload;
	}

	private static function normalize_headers( $headers ): array {
		return self::parse_string_list( $headers );
	}

	private static function build_mail_trigger_payload( array $payload, array $config, string $status, array $extra = [] ) {
		if ( ! self::matches_recipient_filter( $payload['recipient_emails'], $config['recipient_email'] ?? '' ) ) {
			return false;
		}

		return array_merge(
			[
				'success' => true,
				'status'  => $status,
			],
			$extra,
			$payload
		);
	}

	private static function resolve_failed_provider( $handler, array $data ): string {
		if ( is_object( $handler ) && method_exists( $handler, 'getSetting' ) ) {
			$provider = (string) $handler->getSetting( 'provider', '' );
			if ( '' !== $provider ) {
				return $provider;
			}
		}

		$extra = maybe_unserialize( $data['extra'] ?? [] );
		if ( is_array( $extra ) && ! empty( $extra['provider'] ) ) {
			return (string) $extra['provider'];
		}

		return '';
	}

	private static function normalize_content_type( $content_type ): string {
		$content_type = (string) $content_type;
		return in_array( $content_type, self::CONTENT_TYPES, true ) ? $content_type : 'text/html';
	}

	private static function build_email_headers( array $address_groups ): array {
		$headers = [];

		foreach ( $address_groups as $label => $emails ) {
			if ( ! empty( $emails ) ) {
				$headers[] = $label . ': ' . implode( ', ', $emails );
			}
		}

		return $headers;
	}

	private static function matches_recipient_filter( array $recipients, string $required ): bool {
		$required = strtolower( trim( $required ) );
		if ( '' === $required ) {
			return true;
		}

		foreach ( $recipients as $recipient ) {
			if ( strtolower( $recipient ) === $required ) {
				return true;
			}
		}

		return false;
	}

	private static function extract_recipient_emails( $value ): array {
		if ( is_string( $value ) ) {
			$value = maybe_unserialize( $value );
		}

		if ( is_string( $value ) ) {
			return self::parse_email_list( $value );
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$emails = [];
		foreach ( $value as $item ) {
			if ( is_string( $item ) ) {
				$emails = array_merge( $emails, self::parse_email_list( $item ) );
				continue;
			}

			if ( is_array( $item ) ) {
				if ( ! empty( $item['email'] ) ) {
					$emails = array_merge( $emails, self::parse_email_list( $item['email'] ) );
				} else {
					$emails = array_merge( $emails, self::extract_recipient_emails( $item ) );
				}
			}
		}

		return array_values( array_unique( $emails ) );
	}

	private static function parse_email_list( $value ): array {
		$items = self::parse_string_list( $value );
		$emails = [];

		foreach ( $items as $item ) {
			$email = sanitize_email( $item );
			if ( '' !== $email && is_email( $email ) ) {
				$emails[] = $email;
			}
		}

		return array_values( array_unique( $emails ) );
	}

	private static function parse_string_list( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\r\n,]+/', $value ) ?: [];
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$items = [];
		foreach ( $value as $item ) {
			if ( is_array( $item ) ) {
				$items = array_merge( $items, self::parse_string_list( $item ) );
				continue;
			}

			$item = trim( (string) $item );
			if ( '' !== $item ) {
				$items[] = $item;
			}
		}

		return array_values( array_unique( $items ) );
	}

	private static function get_node_event( array $node ): string {
		return (string) ( $node['data']['event'] ?? $node['event'] ?? '' );
	}

	private static function get_node_config( array $node ): array {
		$config = $node['data']['config'] ?? $node['config'] ?? [];
		return is_array( $config ) ? $config : [];
	}

	private static function action_error( string $message, array $input = [] ): array {
		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'error' => $message,
				]
			),
		];
	}

	private static function action_success( array $data = [] ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	private static function get_recipient_filter_field(): array {
		return [
			'key'         => 'recipient_email',
			'label'       => 'Recipient Email',
			'type'        => 'email',
			'placeholder' => 'user@example.com',
			'help'        => 'Optional. Trigger only when this email address is one of the recipients.',
		];
	}

	private static function get_provider_filter_field(): array {
		return [
			'key'         => 'provider',
			'label'       => 'Provider',
			'type'        => 'text',
			'placeholder' => 'smtp, gmail, mailgun',
			'help'        => 'Optional. Trigger only when the failed provider matches this value.',
		];
	}
}
