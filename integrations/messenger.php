<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

/**
 * Facebook Messenger integration.
 *
 * Inbound: Meta webhook → message_received trigger (parsed from entry[].messaging[]).
 * Outbound: Send Message via the Graph API (POST /me/messages with a Page token).
 *
 * Mirrors the WhatsApp integration: GET verify handshake (base class), POST
 * signature verification (HMAC), idempotency dedup, and an echo guard so a
 * reply the Page sends doesn't loop back into the workflow.
 */
class Messenger extends IntegrationBase {

	private const API_BASE_URL        = 'https://graph.facebook.com';
	private const DEFAULT_API_VERSION = 'v19.0';

	public static function get_slug(): string {
		return 'messenger';
	}

	public static function get_name(): string {
		return 'Facebook Messenger';
	}

	public static function get_icon(): string {
		return 'messenger.svg';
	}

	/*
	|--------------------------------------------------------------------------
	| TRIGGERS
	|--------------------------------------------------------------------------
	*/

	public static function get_triggers(): array {
		return [
			'message_received' => [
				'label' => 'Message Received',
				'hook'  => 'zaplane/messenger/message_received',
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		if ( 'message_received' === ( $node['event'] ?? '' ) ) {
			$payload = $args[0] ?? [];

			if ( ! is_array( $payload ) || empty( $payload ) ) {
				return false;
			}

			if ( ! empty( $payload['is_echo'] ) ) {
				return false;
			}

			return $payload;
		}

		return false;
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		if ( 'message_received' === $trigger ) {
			return [
				'message_id'   => 'm_abc123',
				'sender_id'    => '24607896878972',
				'recipient_id' => '102990988765432',
				'text'         => 'Hi, do you have this in stock?',
				'timestamp'    => '1700000000000',
			];
		}

		return [];
	}

	/*
	|--------------------------------------------------------------------------
	| INCOMING WEBHOOK
	|--------------------------------------------------------------------------
	*/

	public static function supports_webhook(): bool {
		return true;
	}

	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		$secret = self::get_webhook_app_secret();

		if ( '' === $secret ) {
			return true; // not configured yet — don't block first-time setup
		}

		$signature = $request->get_header( 'x_hub_signature_256' );
		if ( ! $signature ) {
			return false;
		}

		$expected = 'sha256=' . hash_hmac( 'sha256', $request->get_body(), $secret );

		return hash_equals( $expected, $signature );
	}

	public static function parse_webhook_event( \WP_REST_Request $request ): ?array {
		$data = json_decode( $request->get_body(), true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		$messaging = $data['entry'][0]['messaging'][0] ?? null;
		if ( ! is_array( $messaging ) ) {
			return null;
		}

		$message = $messaging['message'] ?? null;

		// Only react to inbound user text — skip delivery/read receipts,
		// postbacks, and our own outgoing echoes.
		if ( ! is_array( $message ) || ! empty( $message['is_echo'] ) ) {
			return null;
		}

		$text = $message['text'] ?? '';
		if ( '' === $text ) {
			return null;
		}

		// Idempotency — Meta retries until it gets a 200.
		$mid = $message['mid'] ?? '';
		if ( $mid ) {
			$seen_key = 'zaplane_fb_seen_' . md5( $mid );
			if ( get_transient( $seen_key ) ) {
				return null;
			}
			set_transient( $seen_key, 1, 5 * MINUTE_IN_SECONDS );
		}

		return [
			'event'   => 'message_received',
			'payload' => [
				'message_id'   => $mid,
				'sender_id'    => $messaging['sender']['id'] ?? '',
				'recipient_id' => $messaging['recipient']['id'] ?? '',
				'text'         => $text,
				'timestamp'    => $messaging['timestamp'] ?? '',
			],
		];
	}

	public static function get_webhook_app_secret(): string {
		$secret = (string) get_option( 'zaplane_webhook_app_secret_messenger', '' );

		return (string) apply_filters( 'zaplane_webhook_app_secret', $secret, 'messenger' );
	}

	/*
	|--------------------------------------------------------------------------
	| CONNECTION / AUTH
	|--------------------------------------------------------------------------
	*/

	public static function requires_connection(): bool {
		return true;
	}

	public static function get_auth_type(): string {
		return 'api_key';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'page_access_token' => [
				'type'        => 'password',
				'label'       => 'Page Access Token',
				'required'    => true,
				'placeholder' => 'EAAxxxxxxx...',
				'help'        => 'A Page access token from your Meta app with pages_messaging permission.',
			],
			'api_version'       => [
				'type'        => 'text',
				'label'       => 'API Version',
				'required'    => false,
				'placeholder' => 'v19.0',
			],
		];
	}

	public static function test_connection( array $credentials ): array {
		$token       = $credentials['page_access_token'] ?? '';
		$api_version = $credentials['api_version'] ?? self::DEFAULT_API_VERSION;

		if ( '' === $token ) {
			return [ 'success' => false, 'message' => 'page_access_token is required', 'details' => [] ];
		}

		$response = wp_remote_get(
			self::API_BASE_URL . '/' . $api_version . '/me?access_token=' . rawurlencode( $token ),
			[ 'timeout' => 20 ]
		);

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'message' => $response->get_error_message(), 'details' => [] ];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['error'] ) ) {
			return [ 'success' => false, 'message' => $body['error']['message'] ?? 'Unknown API error', 'details' => [] ];
		}

		return [
			'success' => true,
			'message' => 'Connected as: ' . ( $body['name'] ?? 'Facebook Page' ),
			'details' => [ 'page_id' => $body['id'] ?? '' ],
		];
	}

	/*
	|--------------------------------------------------------------------------
	| ACTIONS
	|--------------------------------------------------------------------------
	*/

	public static function get_actions(): array {
		return [
			'send_text' => [ 'label' => 'Send Message' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		if ( 'send_text' === $action ) {
			return [
				[
					'key'         => 'recipient_id',
					'label'       => 'Recipient PSID',
					'type'        => 'expression',
					'required'    => true,
					'placeholder' => '{{trigger.sender_id}}',
					'help'        => 'The Page-scoped ID of the person to message.',
				],
				[
					'key'         => 'text',
					'label'       => 'Message Text',
					'type'        => 'textarea',
					'required'    => true,
					'placeholder' => 'Type your reply... use {{variable}} for dynamic values',
				],
			];
		}

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$action      = $node['data']['event'] ?? '';
		$credentials = $node['_connection_credentials'] ?? null;

		if ( ! $credentials || empty( $credentials['page_access_token'] ) ) {
			throw new \Exception( 'No connection credentials available for Messenger' );
		}

		if ( 'send_text' === $action ) {
			return self::action_send_text( $node, $input, $credentials );
		}

		return [ 'port' => 'main', 'data' => $input ];
	}

	private static function action_send_text( array $node, array $input, array $credentials ): array {
		$token       = $credentials['page_access_token'];
		$api_version = $credentials['api_version'] ?? self::DEFAULT_API_VERSION;

		$recipient = $node['data']['config']['recipient_id'] ?? '';
		$text      = $node['data']['config']['text'] ?? '';

		if ( empty( $recipient ) ) {
			throw new \Exception( 'Messenger: recipient PSID is required' );
		}
		if ( '' === trim( (string) $text ) ) {
			throw new \Exception( 'Messenger: message text is required' );
		}

		$payload = [
			'messaging_type' => 'RESPONSE',
			'recipient'      => [ 'id' => $recipient ],
			'message'        => [ 'text' => $text ],
		];

		$url = self::API_BASE_URL . '/' . $api_version . '/me/messages?access_token=' . rawurlencode( $token );

		$response = wp_remote_post( $url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( $payload ),
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Messenger API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['error'] ) ) {
			throw new \Exception( 'Messenger API error: ' . esc_html( $data['error']['message'] ?? 'Unknown error' ) );
		}

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'success'             => true,
				'messenger_message_id' => $data['message_id'] ?? '',
				'recipient_id'        => $recipient,
			] ),
		];
	}
}
