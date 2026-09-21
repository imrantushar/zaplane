<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\ChannelSend;
use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\MetaGraph;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Whatsapp extends IntegrationBase {

	public static function get_slug(): string {
		return 'whatsapp';
	}

	public static function get_name(): string {
		return 'WhatsApp';
	}

	public static function get_icon(): string {
		return 'whatsapp.svg';
	}

	public static function get_triggers(): array {
		return [
			'message_received' => [
				'label' => 'Message Received',
				'hook'  => 'zaplane/whatsapp/message_received',
			],
		];
	}

	public static function get_actions(): array {
		return [
			'send_text'     => [ 'label' => 'Send Text Message' ],
			'send_template' => [ 'label' => 'Send Template Message' ],
			'send_image'    => [ 'label' => 'Send Image' ],
			'send_document' => [ 'label' => 'Send Document' ],
			'send_video'    => [ 'label' => 'Send Video' ],
			'send_audio'    => [ 'label' => 'Send Audio' ],
			'send_location' => [ 'label' => 'Send Location' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$fields = self::action_fields( $action );

		// Every send except a template can answer a customer, so it gets the
		// Inbox setting too (templates start conversations and are never held).
		if ( $fields && 0 === strpos( $action, 'send_' ) && 'send_template' !== $action ) {
			$fields[] = ChannelSend::mode_field();
		}

		return $fields;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function action_fields( string $action ): array {
		$to_field = [
			'key'         => 'to',
			'type'        => 'text',
			'label'       => 'Recipient Phone Number',
			'placeholder' => '15551234567',
			'required'    => true,
			'help'        => 'Phone number in E.164 format without the + sign (e.g. 15551234567).',
		];

		if ( 'send_text' === $action ) {
			return [
				$to_field,
				[
					'key'         => 'body',
					'type'        => 'textarea',
					'label'       => 'Message Body',
					'placeholder' => 'Enter your message... Use {{variable}} for dynamic values',
					'required'    => true,
				],
			];
		}

		if ( 'send_template' === $action ) {
			return [
				$to_field,
				[
					'key'         => 'template_name',
					'type'        => 'text',
					'label'       => 'Template Name',
					'placeholder' => 'hello_world',
					'required'    => true,
					'help'        => 'The approved template name from your WhatsApp Business account.',
				],
				[
					'key'         => 'language_code',
					'type'        => 'text',
					'label'       => 'Language Code',
					'placeholder' => 'en_US',
					'required'    => true,
					'help'        => 'BCP-47 language/locale code (e.g. en_US, es_ES, pt_BR).',
				],
				[
					'key'         => 'components',
					'type'        => 'textarea',
					'label'       => 'Components (JSON)',
					'placeholder' => '[{"type":"body","parameters":[{"type":"text","text":"John"}]}]',
					'required'    => false,
					'help'        => 'Optional JSON array of template component parameters.',
				],
			];
		}//end if

		if ( 'send_image' === $action ) {
			return [
				$to_field,
				[
					'key'         => 'link',
					'type'        => 'text',
					'label'       => 'Image URL',
					'placeholder' => 'https://example.com/image.jpg',
					'required'    => true,
					'help'        => 'Publicly accessible URL to the image (JPEG or PNG).',
				],
				[
					'key'         => 'caption',
					'type'        => 'text',
					'label'       => 'Caption',
					'placeholder' => 'Optional caption text',
					'required'    => false,
				],
			];
		}

		if ( 'send_document' === $action ) {
			return [
				$to_field,
				[
					'key'         => 'link',
					'type'        => 'text',
					'label'       => 'Document URL',
					'placeholder' => 'https://example.com/document.pdf',
					'required'    => true,
					'help'        => 'Publicly accessible URL to the document.',
				],
				[
					'key'         => 'filename',
					'type'        => 'text',
					'label'       => 'Filename',
					'placeholder' => 'document.pdf',
					'required'    => false,
					'help'        => 'Display filename shown to the recipient.',
				],
				[
					'key'         => 'caption',
					'type'        => 'text',
					'label'       => 'Caption',
					'placeholder' => 'Optional caption text',
					'required'    => false,
				],
			];
		}//end if

		if ( 'send_video' === $action ) {
			return [
				$to_field,
				[
					'key'         => 'link',
					'type'        => 'text',
					'label'       => 'Video URL',
					'placeholder' => 'https://example.com/video.mp4',
					'required'    => true,
					'help'        => 'Publicly accessible URL to the video (MP4).',
				],
				[
					'key'         => 'caption',
					'type'        => 'text',
					'label'       => 'Caption',
					'placeholder' => 'Optional caption text',
					'required'    => false,
				],
			];
		}

		if ( 'send_audio' === $action ) {
			return [
				$to_field,
				[
					'key'         => 'link',
					'type'        => 'text',
					'label'       => 'Audio URL',
					'placeholder' => 'https://example.com/audio.mp3',
					'required'    => true,
					'help'        => 'Publicly accessible URL to the audio file (MP3 or OGG).',
				],
			];
		}

		if ( 'send_location' === $action ) {
			return [
				$to_field,
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
				[
					'key'         => 'name',
					'type'        => 'text',
					'label'       => 'Location Name',
					'placeholder' => 'Our Office',
					'required'    => false,
				],
				[
					'key'         => 'address',
					'type'        => 'text',
					'label'       => 'Address',
					'placeholder' => '123 Main St, City, Country',
					'required'    => false,
				],
			];
		}//end if

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$event = $node['event'] ?? '';

		if ( 'message_received' === $event ) {
			// The incoming-webhook controller fires the hook with the parsed
			// payload as the single argument.
			$payload = $args[0] ?? [];

			if ( ! is_array( $payload ) || empty( $payload ) ) {
				return false;
			}

			// Ignore messages echoed from our own business number, which would
			// otherwise loop a reply workflow back onto itself.
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
				'message_id'      => 'wamid.HBgLMTU1NTEyMzQ1NjcVAgARGBI...',
				'from'            => '15551234567',
				'sender_name'     => 'John Doe',
				'type'            => 'text',
				'text'            => 'Hello, I need help with my order',
				'timestamp'       => '1700000000',
				'phone_number_id' => '1234567890',
			];
		}

		return [];
	}

	public static function supports_webhook(): bool {
		return true;
	}

	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		$secret = self::get_webhook_app_secret();

		if ( '' === $secret ) {
			return true;
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

		// Meta batches: several entries and changes, each with several messages.
		$events = [];
		foreach ( (array) ( $data['entry'] ?? [] ) as $entry ) {
			foreach ( (array) ( $entry['changes'] ?? [] ) as $change ) {
				$value = (array) ( $change['value'] ?? [] );
				foreach ( (array) ( $value['messages'] ?? [] ) as $message ) {
					$event = self::message_event( $value, is_array( $message ) ? $message : [] );
					if ( null !== $event ) {
						$events[] = $event;
					}
				}
			}
		}

		// No inbound message (status update, template event, etc.) → skip.
		return empty( $events ) ? null : [ 'events' => $events ];
	}

	/**
	 * @param array<string,mixed> $value   The change's value block.
	 * @param array<string,mixed> $message One message from it.
	 */
	private static function message_event( array $value, array $message ): ?array {
		// Idempotency: Meta retries webhooks until it gets a 200, so the same
		// message id can arrive several times. Skip any id we've already seen
		// within the dedup window to avoid firing the workflow (and replying)
		// twice.
		$message_id = $message['id'] ?? '';
		if ( $message_id ) {
			$seen_key = 'zaplane_wa_seen_' . md5( $message_id );
			if ( get_transient( $seen_key ) ) {
				return null;
			}
			set_transient( $seen_key, 1, 5 * MINUTE_IN_SECONDS );
		}

		// The contact block for this sender, not simply the first one.
		$contact = [];
		foreach ( (array) ( $value['contacts'] ?? [] ) as $candidate ) {
			if ( ( $candidate['wa_id'] ?? '' ) === ( $message['from'] ?? '' ) ) {
				$contact = $candidate;
				break;
			}
		}
		if ( empty( $contact ) ) {
			$contact = $value['contacts'][0] ?? [];
		}

		$type = $message['type'] ?? '';

		return [
			'event'   => 'message_received',
			'payload' => [
				'message_id'      => $message['id'] ?? '',
				'from'            => $message['from'] ?? '',
				'sender_name'     => $contact['profile']['name'] ?? '',
				'type'            => $type,
				'text'            => self::message_text( $message ),
				'timestamp'       => $message['timestamp'] ?? '',
				'phone_number_id' => $value['metadata']['phone_number_id'] ?? '',
			],
		];
	}

	/**
	 * The readable text of an inbound message: the body, a button or list
	 * choice, or a media caption.
	 *
	 * @param array<string,mixed> $message
	 */
	public static function message_text( array $message ): string {
		$type = (string) ( $message['type'] ?? '' );

		if ( 'text' === $type ) {
			return (string) ( $message['text']['body'] ?? '' );
		}
		if ( 'button' === $type ) {
			return (string) ( $message['button']['text'] ?? '' );
		}
		if ( 'interactive' === $type ) {
			return (string) ( $message['interactive']['button_reply']['title']
				?? ( $message['interactive']['list_reply']['title'] ?? '' ) );
		}
		if ( in_array( $type, [ 'image', 'video', 'document' ], true ) ) {
			return (string) ( $message[ $type ]['caption'] ?? '' );
		}

		return '';
	}

	public static function get_webhook_setup_fields(): array {
		return [
			[
				'key'      => 'verify_token',
				'label'    => 'Verify Token',
				'type'     => 'password',
				'generate' => true,
				'required' => true,
				'help'     => 'Invent a value, save it here, then paste the same string into Meta → WhatsApp → Configuration → Verify Token. Meta will not accept the callback URL until this matches.',
			],
			[
				'key'      => 'app_secret',
				'label'    => 'App Secret',
				'type'     => 'password',
				'required' => true,
				'help'     => 'Meta app → Settings → Basic → App Secret. Used to check the X-Hub-Signature-256 on every delivery.',
			],
		];
	}

	public static function get_webhook_app_secret(): string {
		$secret = self::get_webhook_setting( 'app_secret', 'zaplane_webhook_app_secret_whatsapp' );

		return (string) apply_filters( 'zaplane_webhook_app_secret', $secret, 'whatsapp' );
	}

	public static function execute_node( array $node, array $input ): array {
		$action      = $node['data']['event'] ?? '';
		$credentials = $node['_connection_credentials'] ?? null;

		if ( ! $credentials ) {
			throw new \Exception( 'No connection credentials available for WhatsApp' );
		}

		$token           = $credentials['access_token'] ?? '';
		$phone_number_id = $credentials['phone_number_id'] ?? '';
		$api_version     = (string) ( $credentials['api_version'] ?? '' );

		if ( empty( $token ) || empty( $phone_number_id ) ) {
			throw new \Exception( 'WhatsApp credentials (access_token and phone_number_id) are required' );
		}

		// Workflows messaging a customer the Inbox also handles: see ChannelSend.
		$context = self::send_context( (string) $action, $node, (string) $phone_number_id );
		if ( $context && 'send_template' !== $action ) {
			// Templates are the business starting a conversation (order updates,
			// reminders), never an answer, so they are not held.
			$reason = ChannelSend::check( $context );
			if ( '' !== $reason ) {
				return ChannelSend::held( $context, $reason, $input );
			}
		}

		$out = null;
		if ( 'send_text' === $action ) {
			$out = self::action_send_text( $node, $input, $token, $phone_number_id, $api_version );
		} elseif ( 'send_template' === $action ) {
			$out = self::action_send_template( $node, $input, $token, $phone_number_id, $api_version );
		} elseif ( in_array( $action, [ 'send_image', 'send_document', 'send_video', 'send_audio' ], true ) ) {
			$out = self::action_send_media( $node, $input, $token, $phone_number_id, $api_version, substr( (string) $action, 5 ) );
		} elseif ( 'send_location' === $action ) {
			$out = self::action_send_location( $node, $input, $token, $phone_number_id, $api_version );
		}

		if ( null !== $out ) {
			if ( $context ) {
				ChannelSend::sent( array_merge( $context, [ 'message_id' => (string) ( $out['data']['whatsapp_message_id'] ?? '' ) ] ) );
			}
			return $out;
		}

		return [
			'port' => 'main',
			'data' => $input,
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
			'access_token'    => [
				'type'        => 'password',
				'label'       => 'Access Token',
				'placeholder' => 'EAAxxxxxxx...',
				'required'    => true,
				'help'        => 'Your WhatsApp Business Cloud API permanent or temporary access token from Meta.',
			],
			'phone_number_id' => [
				'type'        => 'text',
				'label'       => 'Phone Number ID',
				'placeholder' => '1234567890',
				'required'    => true,
				'help'        => 'The Phone Number ID from your Meta for Developers app dashboard.',
			],
			'api_version'     => [
				'type'        => 'text',
				'label'       => 'API Version',
				'placeholder' => MetaGraph::DEFAULT_VERSION,
				'required'    => false,
				'help'        => 'Meta Graph API version. Leave blank to use ' . MetaGraph::DEFAULT_VERSION . '.',
			],
		];
	}

	public static function test_connection( array $credentials ): array {
		$token           = $credentials['access_token'] ?? '';
		$phone_number_id = $credentials['phone_number_id'] ?? '';
		$api_version     = (string) ( $credentials['api_version'] ?? '' );

		if ( empty( $token ) ) {
			return [
				'success' => false,
				'message' => 'access_token is required',
				'details' => [],
			];
		}

		if ( empty( $phone_number_id ) ) {
			return [
				'success' => false,
				'message' => 'phone_number_id is required',
				'details' => [],
			];
		}

		$url      = MetaGraph::url( $phone_number_id, $api_version );
		$response = wp_remote_get(
			$url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => 'Connection test failed: ' . $response->get_error_message(),
				'details' => [],
			];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return [
				'success' => false,
				'message' => $body['error']['message'] ?? 'Unknown API error',
				'details' => [],
			];
		}

		return [
			'success' => true,
			'message' => 'Connected as: ' . ( $body['verified_name'] ?? $body['display_phone_number'] ?? 'WhatsApp Business Account' ),
			'details' => [
				'phone_number_id'      => $body['id'] ?? $phone_number_id,
				'display_phone_number' => $body['display_phone_number'] ?? '',
				'verified_name'        => $body['verified_name'] ?? '',
			],
		];
	}

	/**
	 * What a send step is about to deliver, in ChannelSend's shape; null for
	 * steps that don't message a customer.
	 *
	 * @param array<string,mixed> $node
	 * @return array<string,mixed>|null
	 */
	private static function send_context( string $action, array $node, string $phone_number_id ): ?array {
		$config = (array) ( $node['data']['config'] ?? [] );
		$to     = preg_replace( '/\D+/', '', (string) ( $config['to'] ?? '' ) );
		if ( '' === $to || 0 !== strpos( $action, 'send_' ) ) {
			return null;
		}

		$body        = '';
		$attachments = [];
		switch ( $action ) {
			case 'send_text':
				$body = (string) ( $config['body'] ?? '' );
				break;
			case 'send_template':
				/* translators: %s: WhatsApp template name. */
				$body = sprintf( __( 'Template: %s', 'zaplane' ), (string) ( $config['template_name'] ?? '' ) );
				break;
			case 'send_location':
				$body = trim( implode( ', ', array_filter( [ (string) ( $config['name'] ?? '' ), (string) ( $config['address'] ?? '' ) ] ) ) );
				$body = '📍 ' . ( '' !== $body ? $body : ( $config['latitude'] ?? '' ) . ', ' . ( $config['longitude'] ?? '' ) );
				break;
			default:
				$type          = substr( $action, 5 );
				$body          = (string) ( $config['caption'] ?? '' );
				$attachments[] = [
					'type'     => 'image' === $type ? 'image' : 'file',
					'url'      => (string) ( $config['link'] ?? '' ),
					'filename' => (string) ( $config['filename'] ?? $type ),
				];
		}

		return ChannelSend::context( 'whatsapp', $to, $node, [
			'account_id'  => $phone_number_id,
			'body'        => $body,
			'attachments' => $attachments,
		] );
	}

	private static function action_send_text( array $node, array $input, string $token, string $phone_number_id, string $api_version ): array {
		$to   = $node['data']['config']['to'] ?? '';
		$body = $node['data']['config']['body'] ?? '';

		if ( empty( $to ) ) {
			throw new \Exception( 'WhatsApp: recipient phone number (to) is required' );
		}

		if ( empty( $body ) ) {
			throw new \Exception( 'WhatsApp: message body is required' );
		}

		$payload = [
			'messaging_product' => 'whatsapp',
			'recipient_type'    => 'individual',
			'to'                => $to,
			'type'              => 'text',
			'text'              => [
				'preview_url' => false,
				'body'        => $body,
			],
		];

		$response_body = self::whatsapp_request( $token, $phone_number_id, $api_version, $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'whatsapp_message_id' => $response_body['messages'][0]['id'] ?? '',
					'whatsapp_to'         => $to,
					'whatsapp_status'     => 'sent',
					'whatsapp_timestamp'  => time(),
				]
			),
		];
	}

	private static function action_send_template( array $node, array $input, string $token, string $phone_number_id, string $api_version ): array {
		$to            = $node['data']['config']['to'] ?? '';
		$template_name = $node['data']['config']['template_name'] ?? '';
		$language_code = $node['data']['config']['language_code'] ?? 'en_US';
		$components    = $node['data']['config']['components'] ?? '';

		if ( empty( $to ) ) {
			throw new \Exception( 'WhatsApp: recipient phone number (to) is required' );
		}

		if ( empty( $template_name ) ) {
			throw new \Exception( 'WhatsApp: template name is required' );
		}

		$template = [
			'name'     => $template_name,
			'language' => [ 'code' => $language_code ],
		];

		if ( ! empty( $components ) ) {
			$decoded = json_decode( $components, true );
			if ( is_array( $decoded ) ) {
				$template['components'] = $decoded;
			}
		}

		$payload = [
			'messaging_product' => 'whatsapp',
			'to'                => $to,
			'type'              => 'template',
			'template'          => $template,
		];

		$response_body = self::whatsapp_request( $token, $phone_number_id, $api_version, $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'whatsapp_message_id' => $response_body['messages'][0]['id'] ?? '',
					'whatsapp_to'         => $to,
					'whatsapp_status'     => 'sent',
					'whatsapp_timestamp'  => time(),
				]
			),
		];
	}

	private static function action_send_media( array $node, array $input, string $token, string $phone_number_id, string $api_version, string $type ): array {
		$to      = $node['data']['config']['to'] ?? '';
		$link    = $node['data']['config']['link'] ?? '';
		$caption = $node['data']['config']['caption'] ?? '';

		if ( empty( $to ) ) {
			throw new \Exception( 'WhatsApp: recipient phone number (to) is required' );
		}

		$label_map = [
			'image'    => 'image URL',
			'document' => 'document URL',
			'video'    => 'video URL',
			'audio'    => 'audio URL',
		];

		if ( empty( $link ) ) {
			throw new \Exception( 'WhatsApp: ' . esc_html( $label_map[ $type ] ?? $type . ' URL' ) . ' is required' );
		}

		$media = [ 'link' => $link ];

		if ( ! empty( $caption ) && 'audio' !== $type ) {
			$media['caption'] = $caption;
		}

		if ( 'document' === $type ) {
			$filename = $node['data']['config']['filename'] ?? '';
			if ( ! empty( $filename ) ) {
				$media['filename'] = $filename;
			}
		}

		$payload = [
			'messaging_product' => 'whatsapp',
			'recipient_type'    => 'individual',
			'to'                => $to,
			'type'              => $type,
			$type               => $media,
		];

		$response_body = self::whatsapp_request( $token, $phone_number_id, $api_version, $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'whatsapp_message_id' => $response_body['messages'][0]['id'] ?? '',
					'whatsapp_to'         => $to,
					'whatsapp_status'     => 'sent',
					'whatsapp_timestamp'  => time(),
				]
			),
		];
	}

	private static function action_send_location( array $node, array $input, string $token, string $phone_number_id, string $api_version ): array {
		$to        = $node['data']['config']['to'] ?? '';
		$latitude  = $node['data']['config']['latitude'] ?? '';
		$longitude = $node['data']['config']['longitude'] ?? '';
		$name      = $node['data']['config']['name'] ?? '';
		$address   = $node['data']['config']['address'] ?? '';

		if ( empty( $to ) ) {
			throw new \Exception( 'WhatsApp: recipient phone number (to) is required' );
		}

		if ( '' === $latitude || null === $latitude ) {
			throw new \Exception( 'WhatsApp: latitude is required' );
		}

		if ( '' === $longitude || null === $longitude ) {
			throw new \Exception( 'WhatsApp: longitude is required' );
		}

		$location = [
			'latitude'  => (float) $latitude,
			'longitude' => (float) $longitude,
		];

		if ( ! empty( $name ) ) {
			$location['name'] = $name;
		}

		if ( ! empty( $address ) ) {
			$location['address'] = $address;
		}

		$payload = [
			'messaging_product' => 'whatsapp',
			'recipient_type'    => 'individual',
			'to'                => $to,
			'type'              => 'location',
			'location'          => $location,
		];

		$response_body = self::whatsapp_request( $token, $phone_number_id, $api_version, $payload );

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'whatsapp_message_id' => $response_body['messages'][0]['id'] ?? '',
					'whatsapp_to'         => $to,
					'whatsapp_status'     => 'sent',
					'whatsapp_timestamp'  => time(),
				]
			),
		];
	}

	private static function whatsapp_request( string $token, string $phone_number_id, string $api_version, array $body ): array {
		$url = MetaGraph::url( $phone_number_id . '/messages', $api_version );

		$response = wp_remote_post(
			$url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'WhatsApp API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['error'] ) ) {
			$message = $data['error']['message'] ?? 'Unknown WhatsApp API error';
			throw new \Exception( 'WhatsApp API error: ' . esc_html( $message ) );
		}

		return $data ?? [];
	}
}
