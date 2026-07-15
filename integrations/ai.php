<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\AiHttp;
use Zaplane\Models\Connection;

class Ai extends IntegrationBase {

	private const ANTHROPIC_URL      = 'https://api.anthropic.com/v1/messages';
	private const ANTHROPIC_VERSION  = '2023-06-01';
	private const OPENAI_URL         = 'https://api.openai.com/v1/chat/completions';
	private const GEMINI_URL         = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
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
					[
						'value' => 'gemini',
						'label' => 'Google Gemini',
					],
					[
						'value' => 'openai_compatible',
						'label' => 'OpenAI-compatible (Azure, OpenRouter, Ollama, local…)',
					],
				],
				'help'     => 'WordPress Core AI uses the provider/credentials configured in WordPress (no key needed here). Choose another provider to use your own key.',
			],
			'api_key'  => [
				'type'        => 'password',
				'label'       => 'API Key',
				'required'    => false,
				'placeholder' => 'sk-...',
				// Only asked for when using your own provider — WordPress Core AI
				// authenticates through the site's own AI settings, no key here.
				'depends_on'  => [ 'provider' => [ 'anthropic', 'openai', 'gemini', 'openai_compatible' ] ],
				'help'        => 'Required for Anthropic (sk-ant-…), OpenAI (sk-…), Gemini, or an OpenAI-compatible endpoint. For local servers with no auth, enter any placeholder.',
			],
			'base_url' => [
				'type'        => 'text',
				'label'       => 'Base URL',
				'required'    => false,
				'placeholder' => 'http://localhost:11434/v1',
				// Only for OpenAI-compatible endpoints (Azure/OpenRouter/Ollama/etc).
				'depends_on'  => [ 'provider' => [ 'openai_compatible' ] ],
				'help'        => 'The OpenAI-compatible base URL, e.g. http://localhost:11434/v1 (Ollama) or https://openrouter.ai/api/v1. The /chat/completions path is appended automatically.',
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
			'generate_image'    => [ 'label' => 'Generate Image' ],
			'transcribe'        => [ 'label' => 'Transcribe Audio' ],
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
		$creds    = self::credentials_from_connection( $params['connection_id'] ?? null );
		$provider = strtolower( (string) ( $creds['provider'] ?? '' ) );

		// OpenAI-compatible endpoints expose their own catalogue at GET /models —
		// fetch it live so the list matches whatever the server (Ollama, OpenRouter,
		// Azure, LM Studio…) actually serves.
		if ( 'openai_compatible' === $provider ) {
			$models = self::fetch_compatible_models( (string) ( $creds['base_url'] ?? '' ), (string) ( $creds['api_key'] ?? '' ) );
			return ! empty( $models ) ? $models : [];
		}

		return self::models_for_provider( $provider );
	}

	/**
	 * Resolve the credentials stored on a connection.
	 *
	 * @param mixed $connection_id
	 * @return array<string,mixed>
	 */
	private static function credentials_from_connection( $connection_id ): array {
		$connection_id = (int) $connection_id;
		if ( ! $connection_id ) {
			return [];
		}
		$connection = Connection::find( $connection_id );
		if ( ! $connection ) {
			return [];
		}
		$creds = $connection->getCredentials();
		return is_array( $creds ) ? $creds : [];
	}

	/**
	 * List models from an OpenAI-compatible endpoint (GET {base}/models).
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function fetch_compatible_models( string $base_url, string $api_key ): array {
		$base_url = trim( $base_url );
		if ( '' === $base_url ) {
			return [];
		}
		$res = AiHttp::request(
			rtrim( $base_url, '/' ) . '/models',
			'' !== $api_key ? [ 'Authorization' => 'Bearer ' . $api_key ] : [],
			[],
			[
				'method'  => 'GET',
				'retries' => 0,
				'timeout' => 15,
			]
		);
		if ( null !== $res['error'] ) {
			return [];
		}
		$out = [];
		foreach ( (array) ( $res['data']['data'] ?? [] ) as $m ) {
			$id = (string) ( $m['id'] ?? '' );
			if ( '' !== $id ) {
				$out[] = [
					'value' => $id,
					'label' => $id,
				];
			}
		}
		return $out;
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	private static function models_for_provider( string $provider ): array {
		switch ( $provider ) {
			case 'openai':
				return [
					[
						'value' => 'gpt-4o',
						'label' => 'OpenAI GPT-4o'
					],
					[
						'value' => 'gpt-4o-mini',
						'label' => 'OpenAI GPT-4o mini'
					],
				];
			case 'anthropic':
				return [
					[
						'value' => 'claude-opus-4-8',
						'label' => 'Claude Opus 4.8 (most capable)'
					],
					[
						'value' => 'claude-sonnet-4-6',
						'label' => 'Claude Sonnet 4.6 (balanced)'
					],
					[
						'value' => 'claude-haiku-4-5',
						'label' => 'Claude Haiku 4.5 (fastest)'
					],
				];
			case 'gemini':
			case 'google':
				return [
					[
						'value' => 'gemini-2.0-flash',
						'label' => 'Gemini 2.0 Flash'
					],
					[
						'value' => 'gemini-1.5-pro',
						'label' => 'Gemini 1.5 Pro'
					],
					[
						'value' => 'gemini-1.5-flash',
						'label' => 'Gemini 1.5 Flash'
					],
				];
			case 'wordpress':
				return [
					[
						'value' => 'wordpress-default',
						'label' => 'Site default (managed by WordPress AI)'
					],
				];
			default:
				return [];
		}//end switch
	}

	public static function get_action_config_schema( string $action ): array {
		if ( 'generate_image' === $action ) {
			return self::image_schema();
		}
		if ( 'transcribe' === $action ) {
			return self::transcribe_schema();
		}
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
				'key'         => 'image_url',
				'label'       => 'Image URL (vision, optional)',
				'type'        => 'expression',
				'required'    => false,
				'placeholder' => 'https://example.com/photo.jpg',
				'help'        => 'Attach an image for the model to analyze. Supported on OpenAI, Anthropic, and Gemini vision models.',
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
			[
				'key'     => 'response_format',
				'label'   => 'Response Format',
				'type'    => 'select',
				'default' => 'text',
				'options' => [
					[
						'value' => 'text',
						'label' => 'Text',
					],
					[
						'value' => 'json',
						'label' => 'JSON (structured)',
					],
				],
				'help'    => 'JSON asks the model for a single JSON object and parses it into a `json` output field for use in later steps.',
			],
		];
	}

	/** @return array<int,array<string,mixed>> */
	private static function image_schema(): array {
		return [
			[
				'key'         => 'prompt',
				'label'       => 'Prompt',
				'type'        => 'expression',
				'required'    => true,
				'placeholder' => 'A watercolor logo of a mountain at sunrise',
			],
			[
				'key'         => 'model',
				'label'       => 'Model',
				'type'        => 'expression',
				'required'    => false,
				'placeholder' => 'gpt-image-1',
				'help'        => 'OpenAI image model (e.g. gpt-image-1 or dall-e-3). Requires an OpenAI connection.',
			],
			[
				'key'     => 'size',
				'label'   => 'Size',
				'type'    => 'select',
				'default' => '1024x1024',
				'options' => [
					[
						'value' => '1024x1024',
						'label' => 'Square 1024×1024',
					],
					[
						'value' => '1024x1792',
						'label' => 'Portrait 1024×1792',
					],
					[
						'value' => '1792x1024',
						'label' => 'Landscape 1792×1024',
					],
				],
			],
		];
	}

	/** @return array<int,array<string,mixed>> */
	private static function transcribe_schema(): array {
		return [
			[
				'key'         => 'audio_url',
				'label'       => 'Audio URL',
				'type'        => 'expression',
				'required'    => true,
				'placeholder' => 'https://example.com/voice-note.mp3',
				'help'        => 'A reachable URL to the audio file (mp3, wav, m4a, webm…). Requires an OpenAI connection.',
			],
			[
				'key'         => 'model',
				'label'       => 'Model',
				'type'        => 'expression',
				'required'    => false,
				'placeholder' => 'whisper-1',
			],
			[
				'key'         => 'language',
				'label'       => 'Language (optional)',
				'type'        => 'expression',
				'required'    => false,
				'placeholder' => 'en',
				'help'        => 'ISO-639-1 code to improve accuracy; leave blank to auto-detect.',
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$config      = $node['data']['config'] ?? [];
		$credentials = $node['_connection_credentials'] ?? [];
		$event       = (string) ( $node['data']['event'] ?? 'generate_response' );

		$provider = strtolower( (string) ( $credentials['provider'] ?? 'wordpress' ) );
		$api_key  = $credentials['api_key'] ?? '';

		if ( 'generate_image' === $event ) {
			return self::action_generate_image( $api_key, $config, $input );
		}
		if ( 'transcribe' === $event ) {
			return self::action_transcribe( $api_key, $config, $input );
		}

		// WordPress Core AI uses the site's configured connection — no key here.
		if ( 'wordpress' !== $provider && '' === $api_key ) {
			return self::error( 'No AI connection credentials available.', $input );
		}

		$image_url   = trim( (string) ( $config['image_url'] ?? '' ) );
		$model       = sanitize_text_field( $config['model'] ?? self::DEFAULT_MODEL );
		$system      = (string) ( $config['system_prompt'] ?? '' );
		$user_msg    = (string) ( $config['user_message'] ?? '' );
		$max_tokens  = (int) ( $config['max_tokens'] ?? self::DEFAULT_MAX_TOKENS );
		$temperature = ( isset( $config['temperature'] ) && '' !== $config['temperature'] ) ? (float) $config['temperature'] : null;
		$json        = ( 'json' === ( $config['response_format'] ?? 'text' ) );

		if ( '' === trim( $user_msg ) ) {
			return self::error( 'A user message is required.', $input );
		}

		// Nudge every provider toward a bare JSON object; OpenAI/Gemini also get a
		// native structured-output flag below.
		if ( $json ) {
			$system = trim( $system . "\n\nReturn ONLY a single valid JSON object as your answer — no prose, no markdown code fences." );
		}

		$messages = self::build_messages( $config['history'] ?? '', $user_msg );

		if ( 'wordpress' === $provider ) {
			$result = self::call_wordpress( $system, $messages, $max_tokens, $temperature );
		} elseif ( 'openai' === $provider ) {
			$result = self::call_openai( $api_key, $model, $system, $messages, $max_tokens, $temperature, $json, self::OPENAI_URL, $image_url );
		} elseif ( 'openai_compatible' === $provider ) {
			$base     = trim( (string) ( $credentials['base_url'] ?? '' ) );
			if ( '' === $base ) {
				return self::error( 'A Base URL is required for an OpenAI-compatible connection.', $input );
			}
			$endpoint = rtrim( $base, '/' ) . '/chat/completions';
			$result   = self::call_openai( $api_key, $model, $system, $messages, $max_tokens, $temperature, $json, $endpoint, $image_url );
		} elseif ( 'gemini' === $provider || 'google' === $provider ) {
			$result = self::call_gemini( $api_key, $model, $system, $messages, $max_tokens, $temperature, $json, $image_url );
		} else {
			$result = self::call_anthropic( $api_key, $model, $system, $messages, $max_tokens, $temperature, $image_url );
		}

		if ( isset( $result['error'] ) ) {
			return self::error( $result['error'], $input );
		}

		$data = array_merge(
			$input,
			[
				'success'  => true,
				'reply'    => $result['reply'],
				'provider' => $provider,
				'model'    => $model,
				'format'   => $json ? 'json' : 'text',
				'usage'    => $result['usage'] ?? [
					'input_tokens'  => 0,
					'output_tokens' => 0,
					'total_tokens'  => 0,
				],
				'cost_usd' => AiHttp::estimate_cost( $model, $result['usage'] ?? [] ),
			]
		);

		if ( $json ) {
			$data['json'] = self::decode_json_reply( $result['reply'] );
		}

		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	/**
	 * Best-effort decode of a JSON reply: strips ```json fences and returns the
	 * decoded value, or null when it isn't valid JSON.
	 *
	 * @return mixed
	 */
	private static function decode_json_reply( string $reply ) {
		$trimmed = trim( $reply );
		$trimmed = (string) preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $trimmed );
		$decoded = json_decode( $trimmed, true );
		return ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : null;
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

	private static function call_anthropic( string $api_key, string $model, string $system, array $messages, int $max_tokens, ?float $temperature, string $image_url = '' ): array {
		if ( '' !== $image_url ) {
			$messages = self::attach_image_anthropic( $messages, $image_url );
		}

		$body = [
			'model'      => $model,
			'max_tokens' => $max_tokens > 0 ? $max_tokens : self::DEFAULT_MAX_TOKENS,
			'messages'   => $messages,
		];

		if ( '' !== trim( $system ) ) {
			// Cache the (often large, reused) system prompt to cut token cost.
			$body['system'] = AiHttp::anthropic_cached_text( $system );
		}

		// temperature 400s on Opus 4.7+/Fable — only send where accepted.
		if ( null !== $temperature && self::model_accepts_temperature( $model ) ) {
			$body['temperature'] = $temperature;
		}

		$res = AiHttp::request(
			self::ANTHROPIC_URL,
			[
				'x-api-key'         => $api_key,
				'anthropic-version' => self::ANTHROPIC_VERSION,
				'content-type'      => 'application/json',
			],
			$body
		);

		if ( null !== $res['error'] ) {
			return [ 'error' => 'Anthropic: ' . $res['error'] ];
		}

		$data = $res['data'];

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

		return [
			'reply' => $text,
			'usage' => AiHttp::usage( 'anthropic', $data ),
		];
	}

	private static function call_openai( string $api_key, string $model, string $system, array $messages, int $max_tokens, ?float $temperature, bool $json = false, string $endpoint = self::OPENAI_URL, string $image_url = '' ): array {
		if ( '' !== $image_url ) {
			$messages = self::attach_image_openai( $messages, $image_url );
		}

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

		if ( $json ) {
			$body['response_format'] = [ 'type' => 'json_object' ];
		}

		$res = AiHttp::request(
			$endpoint,
			[
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			$body
		);

		if ( null !== $res['error'] ) {
			return [ 'error' => 'OpenAI: ' . $res['error'] ];
		}

		$data = $res['data'];
		$text = $data['choices'][0]['message']['content'] ?? '';

		if ( '' === $text ) {
			return [ 'error' => 'Empty response from OpenAI.' ];
		}

		return [
			'reply' => $text,
			'usage' => AiHttp::usage( 'openai', $data ),
		];
	}

	private static function call_gemini( string $api_key, string $model, string $system, array $messages, int $max_tokens, ?float $temperature, bool $json = false, string $image_url = '' ): array {
		// Gemini uses role "model" for the assistant and nests text in parts.
		$contents = [];
		foreach ( $messages as $m ) {
			$contents[] = [
				'role'  => ( 'assistant' === ( $m['role'] ?? '' ) ) ? 'model' : 'user',
				'parts' => [ [ 'text' => (string) ( $m['content'] ?? '' ) ] ],
			];
		}

		// Gemini takes image bytes inline; fetch and base64-encode the URL.
		if ( '' !== $image_url && ! empty( $contents ) ) {
			$inline = self::fetch_inline_image( $image_url );
			if ( null !== $inline ) {
				$contents[ count( $contents ) - 1 ]['parts'][] = [ 'inline_data' => $inline ];
			}
		}

		$body = [
			'contents'         => $contents,
			'generationConfig' => [
				'maxOutputTokens' => $max_tokens > 0 ? $max_tokens : self::DEFAULT_MAX_TOKENS,
			],
		];
		if ( '' !== trim( $system ) ) {
			$body['systemInstruction'] = [ 'parts' => [ [ 'text' => $system ] ] ];
		}
		if ( null !== $temperature ) {
			$body['generationConfig']['temperature'] = $temperature;
		}
		if ( $json ) {
			$body['generationConfig']['responseMimeType'] = 'application/json';
		}

		$res = AiHttp::request(
			sprintf( self::GEMINI_URL, rawurlencode( $model ) ),
			[
				'Content-Type'   => 'application/json',
				'x-goog-api-key' => $api_key,
			],
			$body
		);

		if ( null !== $res['error'] ) {
			return [ 'error' => 'Gemini: ' . $res['error'] ];
		}

		$data = $res['data'];
		$text = '';
		foreach ( (array) ( $data['candidates'][0]['content']['parts'] ?? [] ) as $part ) {
			$text .= (string) ( $part['text'] ?? '' );
		}

		if ( '' === $text ) {
			return [ 'error' => 'Empty response from Gemini.' ];
		}

		return [
			'reply' => $text,
			'usage' => AiHttp::usage( 'gemini', $data ),
		];
	}

	/**
	 * Attach an image to the last user turn in OpenAI's content-array format.
	 *
	 * @param array<int,array<string,mixed>> $messages
	 * @return array<int,array<string,mixed>>
	 */
	private static function attach_image_openai( array $messages, string $url ): array {
		for ( $i = count( $messages ) - 1; $i >= 0; $i-- ) {
			if ( 'user' === ( $messages[ $i ]['role'] ?? '' ) ) {
				$text                       = is_string( $messages[ $i ]['content'] ) ? $messages[ $i ]['content'] : '';
				$messages[ $i ]['content'] = [
					[
						'type' => 'text',
						'text' => $text,
					],
					[
						'type'      => 'image_url',
						'image_url' => [ 'url' => $url ],
					],
				];
				break;
			}
		}
		return $messages;
	}

	/**
	 * Attach an image to the last user turn in Anthropic's block format (URL source).
	 *
	 * @param array<int,array<string,mixed>> $messages
	 * @return array<int,array<string,mixed>>
	 */
	private static function attach_image_anthropic( array $messages, string $url ): array {
		for ( $i = count( $messages ) - 1; $i >= 0; $i-- ) {
			if ( 'user' === ( $messages[ $i ]['role'] ?? '' ) ) {
				$text                       = is_string( $messages[ $i ]['content'] ) ? $messages[ $i ]['content'] : '';
				$messages[ $i ]['content'] = [
					[
						'type' => 'text',
						'text' => $text,
					],
					[
						'type'   => 'image',
						'source' => [
							'type' => 'url',
							'url'  => $url,
						],
					],
				];
				break;
			}
		}
		return $messages;
	}

	/**
	 * Download an image and return it as Gemini inline_data (base64 + mime type),
	 * or null if it can't be fetched.
	 *
	 * @return array{mime_type:string,data:string}|null
	 */
	private static function fetch_inline_image( string $url ): ?array {
		$resp = wp_remote_get( $url, [ 'timeout' => 30 ] );
		if ( is_wp_error( $resp ) || (int) wp_remote_retrieve_response_code( $resp ) >= 400 ) {
			return null;
		}
		$bytes = wp_remote_retrieve_body( $resp );
		if ( '' === $bytes ) {
			return null;
		}
		$mime = wp_remote_retrieve_header( $resp, 'content-type' );
		if ( is_array( $mime ) ) {
			$mime = $mime[0] ?? '';
		}
		$mime = trim( explode( ';', (string) $mime )[0] );
		if ( '' === $mime ) {
			$mime = 'image/jpeg';
		}
		return [
			'mime_type' => $mime,
			'data'      => base64_encode( $bytes ),
		];
	}

	/**
	 * Generate an image via OpenAI's images API. Returns a URL and/or base64.
	 *
	 * @param array<string,mixed> $config
	 */
	private static function action_generate_image( string $api_key, array $config, array $input ): array {
		if ( '' === trim( $api_key ) ) {
			return self::error( 'Image generation requires an OpenAI connection.', $input );
		}
		$prompt = trim( (string) ( $config['prompt'] ?? '' ) );
		if ( '' === $prompt ) {
			return self::error( 'A prompt is required.', $input );
		}
		$model = sanitize_text_field( (string) ( $config['model'] ?? '' ) );
		$model = '' !== $model ? $model : 'gpt-image-1';
		$size  = sanitize_text_field( (string) ( $config['size'] ?? '1024x1024' ) );
		$size  = '' !== $size ? $size : '1024x1024';

		$res = AiHttp::request(
			'https://api.openai.com/v1/images/generations',
			[
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			[
				'model'  => $model,
				'prompt' => $prompt,
				'size'   => $size,
				'n'      => 1,
			]
		);

		if ( null !== $res['error'] ) {
			return self::error( 'OpenAI image: ' . $res['error'], $input );
		}

		$item = $res['data']['data'][0] ?? [];
		$url  = (string) ( $item['url'] ?? '' );
		$b64  = (string) ( $item['b64_json'] ?? '' );
		if ( '' === $url && '' === $b64 ) {
			return self::error( 'No image was returned.', $input );
		}

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'success'   => true,
					'image_url' => $url,
					'image_b64' => $b64,
					'model'     => $model,
				]
			),
		];
	}

	/**
	 * Transcribe an audio file via OpenAI's transcription API.
	 *
	 * @param array<string,mixed> $config
	 */
	private static function action_transcribe( string $api_key, array $config, array $input ): array {
		if ( '' === trim( $api_key ) ) {
			return self::error( 'Transcription requires an OpenAI connection.', $input );
		}
		$audio_url = trim( (string) ( $config['audio_url'] ?? '' ) );
		if ( '' === $audio_url ) {
			return self::error( 'An audio URL is required.', $input );
		}

		$resp = wp_remote_get( $audio_url, [ 'timeout' => 60 ] );
		if ( is_wp_error( $resp ) || (int) wp_remote_retrieve_response_code( $resp ) >= 400 ) {
			return self::error( 'Could not download the audio file.', $input );
		}
		$bytes = wp_remote_retrieve_body( $resp );
		if ( '' === $bytes ) {
			return self::error( 'The audio file was empty.', $input );
		}

		$path     = (string) wp_parse_url( $audio_url, PHP_URL_PATH );
		$filename = '' !== basename( $path ) ? basename( $path ) : 'audio.mp3';
		$model    = sanitize_text_field( (string) ( $config['model'] ?? '' ) );
		$model    = '' !== $model ? $model : 'whisper-1';

		$fields = [ 'model' => $model ];
		$lang   = sanitize_text_field( (string) ( $config['language'] ?? '' ) );
		if ( '' !== $lang ) {
			$fields['language'] = $lang;
		}

		$res = AiHttp::multipart(
			'https://api.openai.com/v1/audio/transcriptions',
			[ 'Authorization' => 'Bearer ' . $api_key ],
			$fields,
			[
				'file' => [
					'filename' => $filename,
					'content'  => $bytes,
				],
			]
		);

		if ( null !== $res['error'] ) {
			return self::error( 'OpenAI transcription: ' . $res['error'], $input );
		}

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'success' => true,
					'text'    => (string) ( $res['data']['text'] ?? '' ),
					'model'   => $model,
				]
			),
		];
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
