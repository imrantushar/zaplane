<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gmail extends IntegrationBase {

	private const API_BASE_URL = 'https://gmail.googleapis.com/gmail/v1/users/me';

	public static function get_slug(): string {
		return 'gmail';
	}

	public static function get_name(): string {
		return 'Gmail';
	}

	public static function get_icon(): string {
		return 'gmail.svg';
	}

	public static function get_triggers(): array {
		return [];
	}

	public static function get_actions(): array {
		return [
			'send_email'   => [ 'label' => 'Send Email' ],
			'send_reply'   => [ 'label' => 'Send Reply' ],
			'create_draft' => [ 'label' => 'Create Draft' ],
			'add_label'    => [ 'label' => 'Add Label to Message' ],
			'remove_label' => [ 'label' => 'Remove Label from Message' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$compose_fields = [
			[
				'key'         => 'to',
				'type'        => 'email',
				'label'       => 'To',
				'placeholder' => 'recipient@example.com',
				'required'    => true,
				'help'        => 'Recipient email address. Separate multiple addresses with commas.',
			],
			[
				'key'         => 'cc',
				'type'        => 'email',
				'label'       => 'CC',
				'placeholder' => 'cc@example.com',
				'required'    => false,
			],
			[
				'key'         => 'bcc',
				'type'        => 'text',
				'label'       => 'BCC',
				'placeholder' => 'bcc@example.com',
				'required'    => false,
			],
			[
				'key'         => 'subject',
				'type'        => 'text',
				'label'       => 'Subject',
				'placeholder' => 'Email subject',
				'required'    => true,
			],
			[
				'key'         => 'body',
				'type'        => 'textarea',
				'label'       => 'Body',
				'placeholder' => 'Email body... Use {{variable}} for dynamic values',
				'required'    => true,
			],
			[
				'key'      => 'content_type',
				'type'     => 'select',
				'label'    => 'Content Type',
				'required' => false,
				'options'  => [
					[
						'value' => 'text/plain',
						'label' => 'Plain Text'
					],
					[
						'value' => 'text/html',
						'label' => 'HTML'
					],
				],
			],
		];

		if ( 'send_email' === $action ) {
			return $compose_fields;
		}

		if ( 'send_reply' === $action ) {
			return array_merge(
				$compose_fields,
				[
					[
						'key'         => 'thread_id',
						'type'        => 'text',
						'label'       => 'Thread ID',
						'placeholder' => '{{gmail_thread_id}}',
						'required'    => true,
						'help'        => 'The thread ID of the email to reply to. Use {{gmail_thread_id}} from a trigger or earlier node.',
					],
					[
						'key'         => 'message_id_header',
						'type'        => 'text',
						'label'       => 'Message-ID Header',
						'placeholder' => '{{gmail_message_id_header}}',
						'required'    => false,
						'help'        => 'The Message-ID header of the original email for proper threading.',
					],
				]
			);
		}//end if

		if ( 'create_draft' === $action ) {
			return $compose_fields;
		}

		$label_message_fields = [
			[
				'key'         => 'message_id',
				'type'        => 'text',
				'label'       => 'Message ID',
				'placeholder' => '{{gmail_message_id}}',
				'required'    => true,
				'help'        => 'The Gmail message ID. Use {{gmail_message_id}} from a previous node.',
			],
			[
				'key'      => 'label_ids',
				'type'     => 'text',
				'label'    => 'Label IDs',
				'required' => true,
				'help'     => 'Comma-separated label IDs (e.g. IMPORTANT,Label_123). Use the dynamic labels picker.',
				'dynamic'  => [
					'integration' => 'gmail',
					'query'       => 'gmail_labels',
					'select'      => [ 'value', 'label' ],
					'multiple'    => true,
				],
			],
		];

		if ( 'add_label' === $action || 'remove_label' === $action ) {
			return $label_message_fields;
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ): array {
		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$action      = $node['data']['event'] ?? '';
		$credentials = $node['_connection_credentials'] ?? null;

		if ( ! $credentials ) {
			throw new \Exception( 'No connection credentials available for Gmail' );
		}

		$token = $credentials['access_token'] ?? '';

		if ( empty( $token ) ) {
			throw new \Exception( 'Gmail credentials (access_token) are required' );
		}

		if ( 'send_email' === $action ) {
			return self::action_send_email( $node, $input, $token );
		}

		if ( 'send_reply' === $action ) {
			return self::action_send_reply( $node, $input, $token );
		}

		if ( 'create_draft' === $action ) {
			return self::action_create_draft( $node, $input, $token );
		}

		if ( 'add_label' === $action ) {
			return self::action_modify_labels( $node, $input, $token, 'add' );
		}

		if ( 'remove_label' === $action ) {
			return self::action_modify_labels( $node, $input, $token, 'remove' );
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
		return 'oauth2';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'client_id'     => [
				'type'        => 'text',
				'label'       => 'Client ID',
				'placeholder' => 'Your OAuth 2.0 client ID',
				'required'    => true,
				'help'        => 'From Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client IDs.',
			],
			'client_secret' => [
				'type'        => 'password',
				'label'       => 'Client Secret',
				'placeholder' => 'GOCSPX-xxxx',
				'required'    => true,
				'help'        => 'Found alongside the Client ID in Google Cloud Console.',
			],
		];
	}

	public static function get_oauth_scopes(): array {
		return [
			'https://www.googleapis.com/auth/gmail.send',
			'https://www.googleapis.com/auth/gmail.compose',
			'https://www.googleapis.com/auth/gmail.modify',
			'https://www.googleapis.com/auth/gmail.readonly',
		];
	}

	public static function get_oauth_auth_url( string $redirect_uri, string $state, array $credentials = [] ): ?string {
		$client_id = $credentials['client_id'] ?? '';

		if ( empty( $client_id ) ) {
			return null;
		}

		$params = [
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => implode( ' ', self::get_oauth_scopes() ),
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		];

		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
	}

	public static function exchange_oauth_code( string $code, string $redirect_uri, array $credentials = [] ): array {
		$client_id     = $credentials['client_id'] ?? '';
		$client_secret = $credentials['client_secret'] ?? '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			throw new \Exception( 'Client ID and Client Secret are required for OAuth token exchange' );
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			[
				'body' => [
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => $redirect_uri,
					'grant_type'    => 'authorization_code',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'OAuth token exchange failed: ' . esc_html( $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			throw new \Exception( 'Google OAuth error: ' . esc_html( $body['error_description'] ?? $body['error'] ) );
		}

		return [
			'access_token'  => $body['access_token'] ?? '',
			'refresh_token' => $body['refresh_token'] ?? '',
			'expires_in'    => $body['expires_in'] ?? 3600,
			'token_type'    => $body['token_type'] ?? 'Bearer',
			'scope'         => $body['scope'] ?? '',
		];
	}

	public static function test_connection( array $credentials ): array {
		$token = $credentials['access_token'] ?? '';

		if ( empty( $token ) ) {
			return [
				'success' => false,
				'message' => 'access_token is required',
				'details' => [],
			];
		}

		$response = wp_remote_get(
			self::API_BASE_URL . '/profile',
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
				'message' => $body['error']['message'] ?? 'Unknown Gmail API error',
				'details' => [],
			];
		}

		$email = $body['emailAddress'] ?? '';

		return [
			'success' => true,
			'message' => 'Connected as ' . $email,
			'details' => [
				'email'          => $email,
				'messages_total' => $body['messagesTotal'] ?? 0,
				'threads_total'  => $body['threadsTotal'] ?? 0,
			],
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'gmail_labels' => [ self::class, 'query_labels' ],
		];
	}

	public static function query_labels( array $query ): array {
		$token = $query['credentials']['access_token'] ?? '';

		if ( empty( $token ) ) {
			return [];
		}

		$response = wp_remote_get(
			self::API_BASE_URL . '/labels',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$labels = $body['labels'] ?? [];
		$result = [];

		foreach ( $labels as $label ) {
			$result[] = [
				'value' => $label['id'],
				'label' => $label['name'],
			];
		}

		return $result;
	}

	// ── Private action helpers ────────────────────────────────────────────────

	private static function action_send_email( array $node, array $input, string $token ): array {
		$config = $node['data']['config'] ?? [];

		$to      = $config['to'] ?? '';
		$subject = $config['subject'] ?? '';
		$body    = $config['body'] ?? '';

		if ( empty( $to ) ) {
			throw new \Exception( 'Gmail: recipient (to) is required' );
		}

		if ( empty( $subject ) ) {
			throw new \Exception( 'Gmail: subject is required' );
		}

		if ( empty( $body ) ) {
			throw new \Exception( 'Gmail: body is required' );
		}

		$raw  = self::build_mime_message( $config );
		$data = self::gmail_request( $token, 'POST', '/messages/send', [ 'raw' => $raw ] );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'gmail_message_id' => $data['id'] ?? '',
				'gmail_thread_id'  => $data['threadId'] ?? '',
				'gmail_status'     => 'sent',
			] ),
		];
	}

	private static function action_send_reply( array $node, array $input, string $token ): array {
		$config    = $node['data']['config'] ?? [];
		$to        = $config['to'] ?? '';
		$subject   = $config['subject'] ?? '';
		$body      = $config['body'] ?? '';
		$thread_id = $config['thread_id'] ?? '';

		if ( empty( $to ) ) {
			throw new \Exception( 'Gmail: recipient (to) is required' );
		}

		if ( empty( $subject ) ) {
			throw new \Exception( 'Gmail: subject is required' );
		}

		if ( empty( $body ) ) {
			throw new \Exception( 'Gmail: body is required' );
		}

		if ( empty( $thread_id ) ) {
			throw new \Exception( 'Gmail: thread_id is required for replies' );
		}

		$message_id_header = $config['message_id_header'] ?? '';
		$extra_headers     = [];

		if ( ! empty( $message_id_header ) ) {
			$extra_headers['In-Reply-To'] = $message_id_header;
			$extra_headers['References']  = $message_id_header;
		}

		$raw  = self::build_mime_message( $config, $extra_headers );
		$data = self::gmail_request( $token, 'POST', '/messages/send', [
			'raw'      => $raw,
			'threadId' => $thread_id,
		] );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'gmail_message_id' => $data['id'] ?? '',
				'gmail_thread_id'  => $data['threadId'] ?? '',
				'gmail_status'     => 'sent',
			] ),
		];
	}

	private static function action_create_draft( array $node, array $input, string $token ): array {
		$config  = $node['data']['config'] ?? [];
		$to      = $config['to'] ?? '';
		$subject = $config['subject'] ?? '';
		$body    = $config['body'] ?? '';

		if ( empty( $to ) ) {
			throw new \Exception( 'Gmail: recipient (to) is required' );
		}

		if ( empty( $subject ) ) {
			throw new \Exception( 'Gmail: subject is required' );
		}

		if ( empty( $body ) ) {
			throw new \Exception( 'Gmail: body is required' );
		}

		$raw  = self::build_mime_message( $config );
		$data = self::gmail_request( $token, 'POST', '/drafts', [
			'message' => [ 'raw' => $raw ],
		] );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'gmail_draft_id'   => $data['id'] ?? '',
				'gmail_message_id' => $data['message']['id'] ?? '',
				'gmail_thread_id'  => $data['message']['threadId'] ?? '',
			] ),
		];
	}

	private static function action_modify_labels( array $node, array $input, string $token, string $operation ): array {
		$config     = $node['data']['config'] ?? [];
		$message_id = $config['message_id'] ?? '';
		$label_ids  = $config['label_ids'] ?? '';

		if ( empty( $message_id ) ) {
			throw new \Exception( 'Gmail: message_id is required' );
		}

		if ( empty( $label_ids ) ) {
			throw new \Exception( 'Gmail: at least one label ID is required' );
		}

		$ids = array_values( array_filter( array_map( 'trim', explode( ',', $label_ids ) ) ) );

		$payload = 'add' === $operation
			? [ 'addLabelIds' => $ids ]
			: [ 'removeLabelIds' => $ids ];

		$data = self::gmail_request( $token, 'POST', '/messages/' . $message_id . '/modify', $payload );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'gmail_message_id' => $data['id'] ?? '',
				'gmail_thread_id'  => $data['threadId'] ?? '',
				'gmail_label_ids'  => $data['labelIds'] ?? [],
			] ),
		];
	}

	// ── Internal utilities ────────────────────────────────────────────────────

	/**
	 * Build a base64url-encoded RFC 2822 MIME message string.
	 */
	private static function build_mime_message( array $config, array $extra_headers = [] ): string {
		$to           = $config['to'] ?? '';
		$cc           = $config['cc'] ?? '';
		$bcc          = $config['bcc'] ?? '';
		$subject      = $config['subject'] ?? '';
		$body         = $config['body'] ?? '';
		$content_type = $config['content_type'] ?? 'text/plain';

		$headers  = 'To: ' . $to . "\r\n";

		if ( ! empty( $cc ) ) {
			$headers .= 'Cc: ' . $cc . "\r\n";
		}

		if ( ! empty( $bcc ) ) {
			$headers .= 'Bcc: ' . $bcc . "\r\n";
		}

		$headers .= 'Subject: ' . $subject . "\r\n";
		$headers .= 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-Type: ' . $content_type . '; charset=utf-8' . "\r\n";

		foreach ( $extra_headers as $name => $value ) {
			$headers .= $name . ': ' . $value . "\r\n";
		}

		$raw = $headers . "\r\n" . $body;

		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
	}

	/**
	 * Make an authenticated request to the Gmail REST API.
	 *
	 * @throws \Exception on WP_Error or API error response.
	 */
	private static function gmail_request( string $token, string $method, string $endpoint, array $body = [] ): array {
		$url  = self::API_BASE_URL . $endpoint;
		$args = [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
		];

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = 'GET' === $method
			? wp_remote_get( $url, $args )
			: wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Gmail API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['error'] ) ) {
			$message = $data['error']['message'] ?? 'Unknown Gmail API error';
			throw new \Exception( 'Gmail API error: ' . esc_html( $message ) );
		}

		return $data ?? [];
	}
}
