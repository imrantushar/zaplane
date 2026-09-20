<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Telegram extends IntegrationBase {

	private const API_BASE_URL = 'https://api.telegram.org/bot';

	public static function get_slug(): string {
		return 'telegram';
	}

	public static function get_name(): string {
		return 'Telegram';
	}

	public static function get_icon(): string {
		return 'telegram.svg';
	}

	public static function get_triggers(): array {
		return [
			'message_received' => [
				'label' => 'Message Received',
				'hook'  => 'telegram_webhook_message',
			],
			'command_received' => [
				'label' => 'Command Received',
				'hook'  => 'telegram_webhook_command',
			],
		];
	}

	public static function get_actions(): array {
		return [
			'send_message'  => [ 'label' => 'Send Text Message' ],
			'send_photo'    => [ 'label' => 'Send Photo' ],
			'send_document' => [ 'label' => 'Send Document' ],
			'send_video'    => [ 'label' => 'Send Video' ],
			'send_audio'    => [ 'label' => 'Send Audio' ],
			'send_location' => [ 'label' => 'Send Location' ],
			'pin_message'   => [ 'label' => 'Pin Message' ],
			'send_poll'     => [ 'label' => 'Send Poll' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$chat_id_field = [
			'key'         => 'chat_id',
			'type'        => 'text',
			'label'       => 'Chat ID',
			'placeholder' => '987654321 or @channelname',
			'required'    => true,
			'help'        => 'Telegram chat/user ID or @username for public channels.',
		];

		if ( 'send_message' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'text',
					'type'        => 'textarea',
					'label'       => 'Message Text',
					'placeholder' => 'Enter your message... Use {{variable}} for dynamic values',
					'required'    => true,
				],
				[
					'key'      => 'parse_mode',
					'type'     => 'select',
					'label'    => 'Parse Mode',
					'required' => false,
					'options'  => [
						[
							'value' => '',
							'label' => 'None'
						],
						[
							'value' => 'HTML',
							'label' => 'HTML'
						],
						[
							'value' => 'Markdown',
							'label' => 'Markdown'
						],
						[
							'value' => 'MarkdownV2',
							'label' => 'MarkdownV2'
						],
					],
					'help'     => 'Formatting mode for the message text.',
				],
				[
					'key'      => 'disable_notification',
					'type'     => 'select',
					'label'    => 'Silent Message',
					'required' => false,
					'options'  => [
						[
							'value' => 'false',
							'label' => 'No (with notification)'
						],
						[
							'value' => 'true',
							'label' => 'Yes (silent)'
						],
					],
				],
			];
		}//end if

		if ( 'send_photo' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'photo',
					'type'        => 'text',
					'label'       => 'Photo URL',
					'placeholder' => 'https://example.com/image.jpg',
					'required'    => true,
					'help'        => 'Publicly accessible URL to a JPG, PNG, GIF, BMP, or WEBP image.',
				],
				[
					'key'         => 'caption',
					'type'        => 'text',
					'label'       => 'Caption',
					'placeholder' => 'Optional caption',
					'required'    => false,
				],
			];
		}

		if ( 'send_document' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'document',
					'type'        => 'text',
					'label'       => 'Document URL',
					'placeholder' => 'https://example.com/file.pdf',
					'required'    => true,
					'help'        => 'Publicly accessible URL to the document.',
				],
				[
					'key'         => 'caption',
					'type'        => 'text',
					'label'       => 'Caption',
					'placeholder' => 'Optional caption',
					'required'    => false,
				],
			];
		}

		if ( 'send_video' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'video',
					'type'        => 'text',
					'label'       => 'Video URL',
					'placeholder' => 'https://example.com/video.mp4',
					'required'    => true,
					'help'        => 'Publicly accessible URL to an MP4 video.',
				],
				[
					'key'         => 'caption',
					'type'        => 'text',
					'label'       => 'Caption',
					'placeholder' => 'Optional caption',
					'required'    => false,
				],
			];
		}

		if ( 'send_audio' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'audio',
					'type'        => 'text',
					'label'       => 'Audio URL',
					'placeholder' => 'https://example.com/audio.mp3',
					'required'    => true,
					'help'        => 'Publicly accessible URL to an MP3 or M4A audio file.',
				],
				[
					'key'         => 'caption',
					'type'        => 'text',
					'label'       => 'Caption',
					'placeholder' => 'Optional caption',
					'required'    => false,
				],
			];
		}

		if ( 'send_location' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'latitude',
					'type'        => 'text',
					'label'       => 'Latitude',
					'placeholder' => '40.7128',
					'required'    => true,
				],
				[
					'key'         => 'longitude',
					'type'        => 'text',
					'label'       => 'Longitude',
					'placeholder' => '-74.0060',
					'required'    => true,
				],
			];
		}

		if ( 'pin_message' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'message_id',
					'type'        => 'text',
					'label'       => 'Message ID',
					'placeholder' => '{{telegram_message_id}}',
					'required'    => true,
					'help'        => 'ID of the message to pin. Use {{telegram_message_id}} from a previous Send Message node.',
				],
				[
					'key'      => 'disable_notification',
					'type'     => 'select',
					'label'    => 'Silent Pin',
					'required' => false,
					'options'  => [
						[
							'value' => 'false',
							'label' => 'No (with notification)'
						],
						[
							'value' => 'true',
							'label' => 'Yes (silent)'
						],
					],
				],
			];
		}//end if

		if ( 'send_poll' === $action ) {
			return [
				$chat_id_field,
				[
					'key'         => 'question',
					'type'        => 'text',
					'label'       => 'Question',
					'placeholder' => 'What do you prefer?',
					'required'    => true,
					'help'        => 'The poll question (1–300 characters).',
				],
				[
					'key'         => 'options',
					'type'        => 'textarea',
					'label'       => 'Options',
					'placeholder' => "Option A\nOption B\nOption C",
					'required'    => true,
					'help'        => 'One option per line (2–10 options required).',
				],
				[
					'key'      => 'is_anonymous',
					'type'     => 'select',
					'label'    => 'Anonymous Poll',
					'required' => false,
					'options'  => [
						[
							'value' => 'true',
							'label' => 'Yes (anonymous)'
						],
						[
							'value' => 'false',
							'label' => 'No (show voters)'
						],
					],
				],
			];
		}//end if

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$update = $args[0] ?? [];

		if ( ! is_array( $update ) || empty( $update ) ) {
			return false;
		}

		$from   = $update['from'] ?? [];
		$chat   = $update['chat'] ?? [];
		$text   = $update['text'] ?? '';

		$base = [
			'telegram_message_id'     => $update['message_id'] ?? '',
			'telegram_text'           => $text,
			'telegram_chat_id'        => $chat['id'] ?? '',
			'telegram_chat_type'      => $chat['type'] ?? '',
			'telegram_from_id'        => $from['id'] ?? '',
			'telegram_from_first_name' => $from['first_name'] ?? '',
			'telegram_from_last_name' => $from['last_name'] ?? '',
			'telegram_from_username'  => $from['username'] ?? '',
			'telegram_date'           => $update['date'] ?? '',
		];

		$event = $node['data']['event'] ?? '';

		if ( 'command_received' === $event ) {
			$parts   = explode( ' ', $text, 2 );
			$command = $parts[0] ?? '';
			$cmd_args = trim( $parts[1] ?? '' );

			$base['telegram_command']      = $command;
			$base['telegram_command_args'] = $cmd_args;
		}

		return $base;
	}

	public static function get_trigger_sample_output( string $event ): array {
		$base = [
			'telegram_message_id'      => 1042,
			'telegram_text'            => 'Hello from Zaplane!',
			'telegram_chat_id'         => 987654321,
			'telegram_chat_type'       => 'private',
			'telegram_from_id'         => 123456789,
			'telegram_from_first_name' => 'Jane',
			'telegram_from_last_name'  => 'Doe',
			'telegram_from_username'   => 'janedoe',
			'telegram_date'            => 1782633600,
		];

		$command = array_merge(
			$base,
			[
				'telegram_text'         => '/start welcome',
				'telegram_command'      => '/start',
				'telegram_command_args' => 'welcome',
			]
		);

		$samples = [
			'message_received' => $base,
			'command_received' => $command,
		];

		if ( isset( $samples[ $event ] ) ) {
			return $samples[ $event ];
		}

		if ( false !== strpos( $event, 'command' ) ) {
			return $command;
		}

		return $base;
	}

	public static function execute_node( array $node, array $input ): array {
		$action      = $node['data']['event'] ?? '';
		$credentials = $node['_connection_credentials'] ?? null;

		if ( ! $credentials ) {
			throw new \Exception( 'No connection credentials available for Telegram' );
		}

		$token = $credentials['bot_token'] ?? '';

		if ( empty( $token ) ) {
			throw new \Exception( 'Telegram credentials (bot_token) are required' );
		}

		if ( 'send_message' === $action ) {
			return self::action_send_message( $node, $input, $token );
		}

		if ( 'send_photo' === $action ) {
			return self::action_send_media( $node, $input, $token, 'photo', 'sendPhoto' );
		}

		if ( 'send_document' === $action ) {
			return self::action_send_media( $node, $input, $token, 'document', 'sendDocument' );
		}

		if ( 'send_video' === $action ) {
			return self::action_send_media( $node, $input, $token, 'video', 'sendVideo' );
		}

		if ( 'send_audio' === $action ) {
			return self::action_send_media( $node, $input, $token, 'audio', 'sendAudio' );
		}

		if ( 'send_location' === $action ) {
			return self::action_send_location( $node, $input, $token );
		}

		if ( 'pin_message' === $action ) {
			return self::action_pin_message( $node, $input, $token );
		}

		if ( 'send_poll' === $action ) {
			return self::action_send_poll( $node, $input, $token );
		}

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	/**
	 * Telegram pushes updates to a callback URL you register with setWebhook.
	 * Without this the incoming-webhook endpoint rejected every delivery with
	 * "does not support incoming webhooks", so telegram_webhook_message /
	 * telegram_webhook_command could never fire at all.
	 */
	public static function supports_webhook(): bool {
		return true;
	}

	public static function get_webhook_setup_fields(): array {
		return [
			[
				'key'      => 'secret_token',
				'label'    => 'Secret Token',
				'type'     => 'password',
				'generate' => true,
				'help'     => 'Pass the same value as secret_token when you call setWebhook. Telegram then sends it back on every request, which is how this endpoint tells real deliveries from forged ones.',
			],
		];
	}

	/**
	 * Telegram echoes the secret_token given to setWebhook in the
	 * X-Telegram-Bot-Api-Secret-Token header. Unset means the site owner hasn't
	 * configured one yet — allowed so first-time setup isn't a chicken-and-egg,
	 * but the setup panel flags it.
	 */
	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		$secret = self::get_webhook_setting( 'secret_token' );

		if ( '' === $secret ) {
			return true;
		}

		$provided = (string) $request->get_header( 'x_telegram_bot_api_secret_token' );

		return '' !== $provided && hash_equals( $secret, $provided );
	}

	/**
	 * Unwrap Telegram's Update envelope down to the message object that
	 * resolve_trigger() expects, and route commands separately from plain text.
	 */
	public static function parse_webhook_event( \WP_REST_Request $request ): ?array {
		$update = $request->get_json_params();

		if ( ! is_array( $update ) ) {
			$decoded = json_decode( (string) $request->get_body(), true );
			$update  = is_array( $decoded ) ? $decoded : [];
		}

		$message = null;
		foreach ( [ 'message', 'edited_message', 'channel_post', 'edited_channel_post' ] as $key ) {
			if ( isset( $update[ $key ] ) && is_array( $update[ $key ] ) ) {
				$message = $update[ $key ];
				break;
			}
		}

		// Ignore everything that isn't a text message — callback queries, polls,
		// join/leave notices, edits with no text.
		if ( null === $message || '' === (string) ( $message['text'] ?? '' ) ) {
			return null;
		}

		// Never react to another bot's messages (or our own) — that loops.
		if ( ! empty( $message['from']['is_bot'] ) ) {
			return null;
		}

		$text  = (string) $message['text'];
		$event = 0 === strpos( $text, '/' ) ? 'command_received' : 'message_received';

		return [
			'event'   => $event,
			'payload' => $message,
		];
	}

	public static function requires_connection(): bool {
		return true;
	}

	public static function get_auth_type(): string {
		return 'api_key';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'bot_token' => [
				'type'        => 'password',
				'label'       => 'Bot Token',
				'placeholder' => '123456789:AABBCCDDEEFFxxxxxxxxxxxx',
				'required'    => true,
				'help'        => 'The token you received from @BotFather when creating your bot.',
			],
		];
	}

	public static function test_connection( array $credentials ): array {
		$token = $credentials['bot_token'] ?? '';

		if ( empty( $token ) ) {
			return [
				'success' => false,
				'message' => 'bot_token is required',
				'details' => [],
			];
		}

		$response = wp_remote_get( self::API_BASE_URL . $token . '/getMe' );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => 'Connection test failed: ' . $response->get_error_message(),
				'details' => [],
			];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ok'] ) ) {
			return [
				'success' => false,
				'message' => $body['description'] ?? 'Unknown Telegram API error',
				'details' => [],
			];
		}

		$bot = $body['result'] ?? [];

		return [
			'success' => true,
			'message' => 'Connected as @' . ( $bot['username'] ?? 'unknown' ),
			'details' => [
				'bot_id'    => $bot['id'] ?? '',
				'username'  => $bot['username'] ?? '',
				'name'      => $bot['first_name'] ?? '',
			],
		];
	}

	// ── Private action helpers ────────────────────────────────────────────────

	private static function action_send_message( array $node, array $input, string $token ): array {
		$chat_id = $node['data']['config']['chat_id'] ?? '';
		$text    = $node['data']['config']['text'] ?? '';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		if ( empty( $text ) ) {
			throw new \Exception( 'Telegram: message text is required' );
		}

		$payload = [
			'chat_id' => $chat_id,
			'text'    => $text,
		];

		$parse_mode = $node['data']['config']['parse_mode'] ?? '';
		if ( ! empty( $parse_mode ) ) {
			$payload['parse_mode'] = $parse_mode;
		}

		$disable_notification = $node['data']['config']['disable_notification'] ?? 'false';
		if ( 'true' === $disable_notification ) {
			$payload['disable_notification'] = true;
		}

		$body = self::telegram_request( $token, 'sendMessage', $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_message_id' => $body['result']['message_id'] ?? '',
					'telegram_chat_id'    => $chat_id,
					'telegram_status'     => 'sent',
					'telegram_timestamp'  => time(),
				]
			),
		];
	}

	private static function action_send_media( array $node, array $input, string $token, string $field, string $method ): array {
		$chat_id   = $node['data']['config']['chat_id'] ?? '';
		$media_url = $node['data']['config'][ $field ] ?? '';
		$caption   = $node['data']['config']['caption'] ?? '';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		$label_map = [
			'photo'    => 'photo URL',
			'document' => 'document URL',
			'video'    => 'video URL',
			'audio'    => 'audio URL',
		];

		if ( empty( $media_url ) ) {
			throw new \Exception( 'Telegram: ' . esc_html( $label_map[ $field ] ?? $field . ' URL' ) . ' is required' );
		}

		$payload = [
			'chat_id' => $chat_id,
			$field    => $media_url,
		];

		if ( ! empty( $caption ) ) {
			$payload['caption'] = $caption;
		}

		$body = self::telegram_request( $token, $method, $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_message_id' => $body['result']['message_id'] ?? '',
					'telegram_chat_id'    => $chat_id,
					'telegram_status'     => 'sent',
					'telegram_timestamp'  => time(),
				]
			),
		];
	}

	private static function action_send_location( array $node, array $input, string $token ): array {
		$chat_id   = $node['data']['config']['chat_id'] ?? '';
		$latitude  = $node['data']['config']['latitude'] ?? '';
		$longitude = $node['data']['config']['longitude'] ?? '';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		if ( $latitude === '' ) {
			throw new \Exception( 'Telegram: latitude is required' );
		}

		if ( $longitude === '' ) {
			throw new \Exception( 'Telegram: longitude is required' );
		}

		$payload = [
			'chat_id'   => $chat_id,
			'latitude'  => (float) $latitude,
			'longitude' => (float) $longitude,
		];

		$body = self::telegram_request( $token, 'sendLocation', $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_message_id' => $body['result']['message_id'] ?? '',
					'telegram_chat_id'    => $chat_id,
					'telegram_status'     => 'sent',
					'telegram_timestamp'  => time(),
				]
			),
		];
	}

	private static function action_pin_message( array $node, array $input, string $token ): array {
		$chat_id    = $node['data']['config']['chat_id'] ?? '';
		$message_id = $node['data']['config']['message_id'] ?? '';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		if ( empty( $message_id ) ) {
			throw new \Exception( 'Telegram: message_id is required' );
		}

		$payload = [
			'chat_id'    => $chat_id,
			'message_id' => (int) $message_id,
		];

		$disable_notification = $node['data']['config']['disable_notification'] ?? 'false';
		if ( 'true' === $disable_notification ) {
			$payload['disable_notification'] = true;
		}

		self::telegram_request( $token, 'pinChatMessage', $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_pinned'  => true,
					'telegram_chat_id' => $chat_id,
				]
			),
		];
	}

	private static function action_send_poll( array $node, array $input, string $token ): array {
		$chat_id      = $node['data']['config']['chat_id'] ?? '';
		$question     = $node['data']['config']['question'] ?? '';
		$options_raw  = $node['data']['config']['options'] ?? '';

		if ( empty( $chat_id ) ) {
			throw new \Exception( 'Telegram: chat_id is required' );
		}

		if ( empty( $question ) ) {
			throw new \Exception( 'Telegram: poll question is required' );
		}

		if ( empty( $options_raw ) ) {
			throw new \Exception( 'Telegram: poll options are required' );
		}

		$options = array_values(
			array_filter(
				array_map( 'trim', explode( "\n", $options_raw ) )
			)
		);

		$payload = [
			'chat_id'  => $chat_id,
			'question' => $question,
			'options'  => $options,
		];

		$is_anonymous = $node['data']['config']['is_anonymous'] ?? 'true';
		$payload['is_anonymous'] = ( 'true' === $is_anonymous );

		$body = self::telegram_request( $token, 'sendPoll', $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'telegram_message_id' => $body['result']['message_id'] ?? '',
					'telegram_chat_id'    => $chat_id,
					'telegram_status'     => 'sent',
					'telegram_timestamp'  => time(),
				]
			),
		];
	}

	/**
	 * Call a Telegram Bot API method.
	 *
	 * @throws \Exception on WP_Error or API error.
	 */
	private static function telegram_request( string $token, string $method, array $payload ): array {
		$url = self::API_BASE_URL . $token . '/' . $method;

		$response = wp_remote_post(
			$url,
			[
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Telegram API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['ok'] ) ) {
			$message = $data['description'] ?? 'Unknown Telegram API error';
			throw new \Exception( 'Telegram API error: ' . esc_html( $message ) );
		}

		return $data;
	}
}
