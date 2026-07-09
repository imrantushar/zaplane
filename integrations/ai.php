<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Models\Connection;

class Ai extends IntegrationBase {

	private const ANTHROPIC_URL      = 'https://api.anthropic.com/v1/messages';
	private const ANTHROPIC_VERSION  = '2023-06-01';
	private const OPENAI_URL         = 'https://api.openai.com/v1/chat/completions';
	private const DEFAULT_MODEL      = 'claude-opus-4-8';
	private const DEFAULT_MAX_TOKENS = 2048;

	public static function get_slug(): string {
		return 'ai';
	}

	public static function get_name(): string {
		return 'AI';
	}

	public static function get_icon(): string {
		return 'ai.svg';
	}

	public static function get_category(): string {
		return 'app';
	}

	public static function get_triggers(): array {
		return [];
	}

	public static function requires_connection(): bool {
		return true;
	}

	public static function get_auth_type(): string {
		return 'api_key';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'provider' => [
				'type'     => 'select',
				'label'    => 'Provider',
				'required' => true,
				'default'  => 'wordpress',
				'options'  => [
					[
						'value' => 'wordpress',
						'label' => 'WordPress Core AI (uses site-configured connection)',
					],
					[
						'value' => 'anthropic',
						'label' => 'Anthropic (Claude)',
					],
					[
						'value' => 'openai',
						'label' => 'OpenAI',
					],
				],
				'help'     => 'WordPress Core AI uses the provider/credentials configured in WordPress (no key needed here). Choose Anthropic/OpenAI to use your own key.',
			],
			'api_key'  => [
				'type'        => 'password',
				'label'       => 'API Key',
				'required'    => false,
				'placeholder' => 'sk-...',
				'help'        => 'Required for Anthropic (sk-ant-...) or OpenAI (sk-...). Leave blank for WordPress Core AI.',
			],
		];
	}

	public static function test_connection( array $credentials ): array {
		$provider = strtolower( (string) ( $credentials['provider'] ?? 'wordpress' ) );
		$api_key  = $credentials['api_key'] ?? '';

		if ( 'wordpress' === $provider ) {
			if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
				return [
					'success' => false,
					'message' => 'WordPress Core AI is unavailable (requires WordPress 7.0+).',
					'details' => [],
				];
			}
			if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
				return [
					'success' => false,
					'message' => 'AI features are disabled in this WordPress environment.',
					'details' => [],
				];
			}
			return [
				'success' => true,
				'message' => 'Using WordPress Core AI connection',
				'details' => [ 'provider' => 'wordpress' ],
			];
		}//end if

		if ( '' === $api_key ) {
			return [
				'success' => false,
				'message' => 'API key is required',
				'details' => [],
			];
		}

		if ( 'openai' === $provider ) {
			$response = wp_remote_get(
				'https://api.openai.com/v1/models',
				[
					'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
					'timeout' => 20,
				]
			);
		} else {
			$response = wp_remote_get(
				'https://api.anthropic.com/v1/models',
				[
					'headers' => [
						'x-api-key'         => $api_key,
						'anthropic-version' => self::ANTHROPIC_VERSION,
					],
					'timeout' => 20,
				]
			);
		}

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
				'details' => [],
			];
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$msg  = $body['error']['message'] ?? ( 'Connection test failed (HTTP ' . $code . ')' );
			return [
				'success' => false,
				'message' => $msg,
				'details' => [],
			];
		}

		return [
			'success' => true,
			'message' => 'Connected to ' . ( 'openai' === $provider ? 'OpenAI' : 'Anthropic' ),
			'details' => [ 'provider' => $provider ],
		];
	}

	public static function get_actions(): array {
		return [
			'generate_response' => [ 'label' => 'Generate AI Response' ],
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'ai_models' => [ self::class, 'query_models' ],
		];
	}

	/**
	 * Return the model options for the selected connection's provider.
	 *
	 * The `model` field declares `depends_on => connection_id`, so the drawer
	 * only calls this once a connection is chosen — that's what keeps a mixed
	 * Anthropic/OpenAI list from confusing the user before then.
	 *
	 * @param array<string,mixed> $params
	 * @return array<int,array<string,string>>
	 */
	public static function query_models( array $params ): array {
		$provider = self::provider_from_connection( $params['connection_id'] ?? null );
		return self::models_for_provider( $provider );
	}

	/**
	 * Resolve the provider (anthropic|openai|wordpress|'') stored on a connection.
	 *
	 * @param mixed $connection_id
	 */
	private static function provider_from_connection( $connection_id ): string {
		$connection_id = (int) $connection_id;
		if ( ! $connection_id ) {
			return '';
		}

		$connection = Connection::find( $connection_id );
		if ( ! $connection ) {
			return '';
		}

		$provider = $connection->getCredentials()['provider'] ?? 'wordpress';
		return strtolower( (string) $provider );
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	private static function models_for_provider( string $provider ): array {
		switch ( $provider ) {
			case 'openai':
				return [
					[ 'value' => 'gpt-4o',      'label' => 'OpenAI GPT-4o' ],
					[ 'value' => 'gpt-4o-mini', 'label' => 'OpenAI GPT-4o mini' ],
				];
			case 'anthropic':
				return [
					[ 'value' => 'claude-opus-4-8',   'label' => 'Claude Opus 4.8 (most capable)' ],
					[ 'value' => 'claude-sonnet-4-6', 'label' => 'Claude Sonnet 4.6 (balanced)' ],
					[ 'value' => 'claude-haiku-4-5',  'label' => 'Claude Haiku 4.5 (fastest)' ],
				];
			case 'wordpress':
				return [
					[ 'value' => 'wordpress-default', 'label' => 'Site default (managed by WordPress AI)' ],
				];
			default:
				return [];
		}
	}

	public static function get_action_config_schema( string $action ): array {
		if ( 'generate_response' !== $action ) {
			return [];
		}

		return [
			[
				'key'      => 'model',
				'label'    => 'Model',
				'type'     => 'select',
				'required' => true,
				'dynamic'  => [
					'integration' => 'ai',
					'query'       => 'ai_models',
					'select'      => [ 'value', 'label' ],
					'depends_on'  => [ 'connection_id' ],
				],
				'help'     => 'Select an AI connection first — the list then shows only models for that provider.',
			],
			[
				'key'         => 'system_prompt',
				'label'       => 'System Prompt (persona / business context)',
				'type'        => 'textarea',
				'required'    => false,
				'placeholder' => 'You are a helpful support assistant for Acme Co. Be concise and friendly.',
			],
			[
				'key'         => 'user_message',
				'label'       => 'User Message',
				'type'        => 'expression',
				'required'    => true,
				'placeholder' => '{{trigger.text}}',
				'help'        => 'The incoming message to respond to.',
			],
			[
				'key'      => 'history',
				'label'    => 'Conversation History (optional)',
				'type'     => 'expression',
				'required' => false,
				'help'     => 'A JSON array of prior turns ([{"role":"user|assistant","content":"..."}]) from a memory node.',
			],
			[
				'key'      => 'max_tokens',
				'label'    => 'Max Tokens',
				'type'     => 'number',
				'required' => false,
				'default'  => self::DEFAULT_MAX_TOKENS,
			],
			[
				'key'      => 'temperature',
				'label'    => 'Temperature (OpenAI / Sonnet / Haiku only)',
				'type'     => 'number',
				'required' => false,
				'help'     => 'Ignored for Claude Opus / Fable models, which do not accept it.',
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$config      = $node['data']['config'] ?? [];
		$credentials = $node['_connection_credentials'] ?? [];

		$provider = strtolower( (string) ( $credentials['provider'] ?? 'wordpress' ) );
		$api_key  = $credentials['api_key'] ?? '';

		// WordPress Core AI uses the site's configured connection — no key here.
		if ( 'wordpress' !== $provider && '' === $api_key ) {
			return self::error( 'No AI connection credentials available.', $input );
		}

		$model       = sanitize_text_field( $config['model'] ?? self::DEFAULT_MODEL );
		$system      = (string) ( $config['system_prompt'] ?? '' );
		$user_msg    = (string) ( $config['user_message'] ?? '' );
		$max_tokens  = (int) ( $config['max_tokens'] ?? self::DEFAULT_MAX_TOKENS );
		$temperature = ( isset( $config['temperature'] ) && '' !== $config['temperature'] ) ? (float) $config['temperature'] : null;

		if ( '' === trim( $user_msg ) ) {
			return self::error( 'A user message is required.', $input );
		}

		$messages = self::build_messages( $config['history'] ?? '', $user_msg );

		if ( 'wordpress' === $provider ) {
			$result = self::call_wordpress( $system, $messages, $max_tokens, $temperature );
		} elseif ( 'openai' === $provider ) {
			$result = self::call_openai( $api_key, $model, $system, $messages, $max_tokens, $temperature );
		} else {
			$result = self::call_anthropic( $api_key, $model, $system, $messages, $max_tokens, $temperature );
		}

		if ( isset( $result['error'] ) ) {
			return self::error( $result['error'], $input );
		}

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'success'  => true,
					'reply'    => $result['reply'],
					'provider' => $provider,
					'model'    => $model,
				]
			),
		];
	}

	private static function build_messages( $history, string $user_msg ): array {
		$messages = [];

		if ( is_string( $history ) && '' !== trim( $history ) ) {
			$decoded = json_decode( $history, true );
			if ( is_array( $decoded ) ) {
				$history = $decoded;
			}
		}

		if ( is_array( $history ) ) {
			foreach ( $history as $turn ) {
				if ( ! is_array( $turn ) ) {
					continue;
				}
				$role    = ( 'assistant' === ( $turn['role'] ?? '' ) ) ? 'assistant' : 'user';
				$content = (string) ( $turn['content'] ?? '' );
				if ( '' !== $content ) {
					$messages[] = [
						'role'    => $role,
						'content' => $content,
					];
				}
			}
		}

		$messages[] = [
			'role'    => 'user',
			'content' => $user_msg,
		];

		return $messages;
	}

	private static function model_accepts_temperature( string $model ): bool {
		$model = strtolower( $model );
		if ( false !== strpos( $model, 'opus' ) || false !== strpos( $model, 'fable' ) ) {
			return false;
		}
		return true;
	}

	private static function call_wordpress( string $system, array $messages, int $max_tokens, ?float $temperature ): array {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return [ 'error' => 'WordPress Core AI is unavailable (requires WordPress 7.0+).' ];
		}
		if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
			return [ 'error' => 'AI features are disabled in this WordPress environment.' ];
		}

		// The last message is the current user turn; earlier ones are history.
		$turns  = $messages;
		$last   = array_pop( $turns );
		$prompt = (string) ( $last['content'] ?? '' );

		$transcript = [];
		foreach ( $turns as $m ) {
			$label        = ( 'assistant' === ( $m['role'] ?? '' ) ) ? 'Assistant' : 'User';
			$transcript[] = $label . ': ' . ( $m['content'] ?? '' );
		}

		$sys = $system;
		if ( ! empty( $transcript ) ) {
			$sys = trim( $sys . "\n\nConversation so far:\n" . implode( "\n", $transcript ) );
		}

		try {
			$builder = wp_ai_client_prompt( $prompt );
			if ( '' !== trim( $sys ) ) {
				$builder->using_system_instruction( $sys );
			}
			if ( $max_tokens > 0 ) {
				$builder->using_max_tokens( $max_tokens );
			}
			if ( null !== $temperature ) {
				$builder->using_temperature( $temperature );
			}

			$text = $builder->generate_text();
		} catch ( \Throwable $e ) {
			return [ 'error' => 'WordPress AI error: ' . $e->getMessage() ];
		}

		if ( is_wp_error( $text ) ) {
			return [ 'error' => 'WordPress AI: ' . $text->get_error_message() ];
		}

		if ( ! is_string( $text ) || '' === $text ) {
			return [ 'error' => 'Empty response from WordPress AI.' ];
		}

		return [ 'reply' => $text ];
	}

	private static function call_anthropic( string $api_key, string $model, string $system, array $messages, int $max_tokens, ?float $temperature ): array {
		$body = [
			'model'      => $model,
			'max_tokens' => $max_tokens > 0 ? $max_tokens : self::DEFAULT_MAX_TOKENS,
			'messages'   => $messages,
		];

		if ( '' !== trim( $system ) ) {
			$body['system'] = $system;
		}

		// temperature 400s on Opus 4.7+/Fable — only send where accepted.
		if ( null !== $temperature && self::model_accepts_temperature( $model ) ) {
			$body['temperature'] = $temperature;
		}

		$response = wp_remote_post(
			self::ANTHROPIC_URL,
			[
				'headers' => [
					'x-api-key'         => $api_key,
					'anthropic-version' => self::ANTHROPIC_VERSION,
					'content-type'      => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
				'timeout' => 120,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [ 'error' => 'Anthropic request failed: ' . $response->get_error_message() ];
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['error'] ) ) {
			return [ 'error' => 'Anthropic API error: ' . ( $data['error']['message'] ?? 'unknown' ) ];
		}

		// Safety classifiers can decline with HTTP 200 + stop_reason "refusal".
		if ( ( $data['stop_reason'] ?? '' ) === 'refusal' ) {
			return [ 'error' => 'The request was declined by the model safety system.' ];
		}

		$text = '';
		if ( ! empty( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $block ) {
				if ( ( $block['type'] ?? '' ) === 'text' ) {
					$text .= $block['text'] ?? '';
				}
			}
		}

		if ( '' === $text ) {
			return [ 'error' => 'Empty response from Anthropic.' ];
		}

		return [ 'reply' => $text ];
	}

	private static function call_openai( string $api_key, string $model, string $system, array $messages, int $max_tokens, ?float $temperature ): array {
		$chat = [];
		if ( '' !== trim( $system ) ) {
			$chat[] = [
				'role'    => 'system',
				'content' => $system,
			];
		}
		foreach ( $messages as $m ) {
			$chat[] = $m;
		}

		$body = [
			'model'      => $model,
			'messages'   => $chat,
			'max_tokens' => $max_tokens > 0 ? $max_tokens : self::DEFAULT_MAX_TOKENS,
		];

		if ( null !== $temperature ) {
			$body['temperature'] = $temperature;
		}

		$response = wp_remote_post(
			self::OPENAI_URL,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
				'timeout' => 120,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [ 'error' => 'OpenAI request failed: ' . $response->get_error_message() ];
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['error'] ) ) {
			return [ 'error' => 'OpenAI API error: ' . ( $data['error']['message'] ?? 'unknown' ) ];
		}

		$text = $data['choices'][0]['message']['content'] ?? '';

		if ( '' === $text ) {
			return [ 'error' => 'Empty response from OpenAI.' ];
		}

		return [ 'reply' => $text ];
	}

	private static function error( string $message, array $input ): array {
		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'success' => false,
					'error'   => $message,
				]
			),
		];
	}
}
