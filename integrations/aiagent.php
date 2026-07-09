<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Aiagent extends IntegrationBase {

	private const ANTHROPIC_URL     = 'https://api.anthropic.com/v1/messages';
	private const ANTHROPIC_VERSION = '2023-06-01';
	private const OPENAI_URL        = 'https://api.openai.com/v1/chat/completions';
	private const DEFAULT_MODEL     = 'claude-opus-4-8';
	private const DEFAULT_MAX_STEPS = 5;

	public static function get_slug(): string {
		return 'ai-agent';
	}

	public static function get_name(): string {
		return 'AI Agent';
	}

	public static function get_icon(): string {
		return 'ai-agent.svg';
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
				'default'  => 'anthropic',
				'options'  => [
					[
						'value' => 'anthropic',
						'label' => 'Anthropic (Claude)'
					],
					[
						'value' => 'openai',
						'label' => 'OpenAI'
					],
				],
				'help'     => 'The AI Agent needs tool-calling, so it uses an Anthropic or OpenAI key (not WordPress Core AI).',
			],
			'api_key'  => [
				'type'     => 'password',
				'label'    => 'API Key',
				'required' => true,
			],
		];
	}

	public static function get_actions(): array {
		return [
			'run_agent' => [ 'label' => 'Run AI Agent' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		if ( 'run_agent' !== $action ) {
			return [];
		}

		return [
			[
				'key'      => 'model',
				'label'    => 'Model',
				'type'     => 'select',
				'required' => true,
				'default'  => self::DEFAULT_MODEL,
				'options'  => [
					[
						'value' => 'claude-opus-4-8',
						'label' => 'Claude Opus 4.8'
					],
					[
						'value' => 'claude-sonnet-4-6',
						'label' => 'Claude Sonnet 4.6'
					],
					[
						'value' => 'gpt-4o',
						'label' => 'OpenAI GPT-4o'
					],
				],
			],
			[
				'key'         => 'system_prompt',
				'label'       => 'Agent Instructions',
				'type'        => 'textarea',
				'required'    => false,
				'placeholder' => 'You are a support agent for Acme. Use the tools to find answers before replying.',
			],
			[
				'key'         => 'task',
				'label'       => 'Task / Message',
				'type'        => 'expression',
				'required'    => true,
				'placeholder' => '{{trigger.text}}',
			],
			[
				'key'         => 'business_key',
				'label'       => 'Knowledge Business Key (enables knowledge search tool)',
				'type'        => 'expression',
				'required'    => false,
				'placeholder' => 'business_a',
			],
			[
				'key'      => 'enable_http',
				'label'    => 'Allow HTTP Request tool',
				'type'     => 'select',
				'required' => false,
				'default'  => 'no',
				'options'  => [
					[
						'value' => 'no',
						'label' => 'No'
					],
					[
						'value' => 'yes',
						'label' => 'Yes'
					],
				],
			],
			[
				'key'      => 'max_steps',
				'label'    => 'Max Tool Steps',
				'type'     => 'number',
				'required' => false,
				'default'  => self::DEFAULT_MAX_STEPS,
			],
			[
				'key'     => 'response_format',
				'label'   => 'Response Format',
				'type'    => 'select',
				'default' => 'text',
				'options' => [
					[ 'value' => 'text', 'label' => 'Text' ],
					[ 'value' => 'json', 'label' => 'JSON (structured)' ],
				],
				'help'    => 'Text returns the reply as-is. JSON asks the model for a structured JSON object and parses it into a `json` output field.',
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$config      = $node['data']['config'] ?? [];
		$credentials = $node['_connection_credentials'] ?? [];

		$provider = $credentials['provider'] ?? 'anthropic';
		$api_key  = $credentials['api_key'] ?? '';

		if ( '' === $api_key ) {
			return self::err( 'No AI Agent connection credentials available.', $input );
		}

		$task = (string) ( $config['task'] ?? '' );
		if ( '' === trim( $task ) ) {
			return self::err( 'A task/message is required.', $input );
		}

		$ctx = [
			'business_key' => trim( (string) ( $config['business_key'] ?? '' ) ),
			'enable_http'  => ( 'yes' === ( $config['enable_http'] ?? 'no' ) ),
		];

		$model     = sanitize_text_field( (string) ( $config['model'] ?? self::DEFAULT_MODEL ) );
		$system    = (string) ( $config['system_prompt'] ?? '' );
		$max_steps = max( 1, (int) ( $config['max_steps'] ?? self::DEFAULT_MAX_STEPS ) );
		$format    = ( 'json' === ( $config['response_format'] ?? 'text' ) ) ? 'json' : 'text';

		// Nudge the model toward pure JSON on top of any provider-native setting.
		if ( 'json' === $format ) {
			$system = trim( $system . "\n\nReturn ONLY a single valid JSON object as your final answer — no prose, no markdown code fences." );
		}

		if ( 'openai' === $provider ) {
			$result = self::run_openai( $api_key, $model, $system, $task, $ctx, $max_steps, 'json' === $format );
		} else {
			$result = self::run_anthropic( $api_key, $model, $system, $task, $ctx, $max_steps );
		}

		if ( isset( $result['error'] ) ) {
			return self::err( $result['error'], $input );
		}

		$data = array_merge( $input, [
			'success'    => true,
			'reply'      => $result['reply'],
			'steps'      => $result['steps'],
			'tool_calls' => $result['tool_calls'],
			'format'     => $format,
		] );

		if ( 'json' === $format ) {
			$data['json'] = self::decode_json_reply( $result['reply'] );
		}

		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	/**
	 * Best-effort decode of a model's JSON reply: strips ```json fences and
	 * returns the decoded array, or null when it isn't valid JSON.
	 *
	 * @return mixed
	 */
	private static function decode_json_reply( string $reply ) {
		$trimmed = trim( $reply );
		$trimmed = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $trimmed );
		$decoded = json_decode( (string) $trimmed, true );
		return ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : null;
	}

	private static function tool_specs( array $ctx ): array {
		$tools = [];

		if ( '' !== $ctx['business_key'] ) {
			$tools[] = [
				'name'        => 'search_knowledge',
				'description' => 'Search the business knowledge base (products, prices, FAQs) for relevant info. Use this before answering questions about the business.',
				'schema'      => [
					'type'       => 'object',
					'properties' => [
						'query' => [
							'type' => 'string',
							'description' => 'What to look up.'
						]
					],
					'required'   => [ 'query' ],
				],
			];
		}

		if ( $ctx['enable_http'] ) {
			$tools[] = [
				'name'        => 'http_request',
				'description' => 'Make an HTTP request to an external API and return the response.',
				'schema'      => [
					'type'       => 'object',
					'properties' => [
						'method' => [
							'type' => 'string',
							'description' => 'GET or POST.'
						],
						'url'    => [ 'type' => 'string' ],
						'body'   => [
							'type' => 'string',
							'description' => 'Optional request body.'
						],
					],
					'required'   => [ 'url' ],
				],
			];
		}//end if

		return $tools;
	}

	private static function run_tool( string $name, array $args, array $ctx ): string {
		if ( 'search_knowledge' === $name ) {
			if ( ! class_exists( '\Zaplane\Integrations\Knowledge' ) ) {
				return 'Knowledge unavailable.';
			}
			$res = \Zaplane\Integrations\Knowledge::execute_node( [
				'data' => [
					'event' => 'retrieve',
					'config' => [
						'business_key' => $ctx['business_key'],
						'query'        => (string) ( $args['query'] ?? '' ),
						'limit'        => 5,
					]
				]
			], [] );
			$context = $res['data']['context'] ?? '';
			return '' !== $context ? $context : 'No matching knowledge found.';
		}

		if ( 'http_request' === $name ) {
			if ( empty( $ctx['enable_http'] ) ) {
				return 'HTTP tool disabled.';
			}
			$url = (string) ( $args['url'] ?? '' );
			if ( '' === $url ) {
				return 'url is required.';
			}
			$method = strtoupper( (string) ( $args['method'] ?? 'GET' ) );
			$resp   = wp_remote_request( $url, [
				'method'  => in_array( $method, [ 'GET', 'POST', 'PUT', 'DELETE' ], true ) ? $method : 'GET',
				'body'    => $args['body'] ?? null,
				'timeout' => 30,
			] );
			if ( is_wp_error( $resp ) ) {
				return 'Request failed: ' . $resp->get_error_message();
			}
			$code = wp_remote_retrieve_response_code( $resp );
			$body = wp_remote_retrieve_body( $resp );
			if ( strlen( $body ) > 2000 ) {
				$body = substr( $body, 0, 2000 ) . '…';
			}
			return "Status: {$code}\n{$body}";
		}//end if

		return 'Unknown tool: ' . $name;
	}

	private static function run_anthropic( string $api_key, string $model, string $system, string $task, array $ctx, int $max_steps ): array {
		$tools = array_map( static fn( $t ) => [
			'name'         => $t['name'],
			'description'  => $t['description'],
			'input_schema' => $t['schema'],
		], self::tool_specs( $ctx ) );

		$messages   = [
			[
				'role' => 'user',
				'content' => $task
			]
		];
		$tool_calls = [];

		for ( $step = 0; $step <= $max_steps; $step++ ) {
			$body = [
				'model'      => $model,
				'max_tokens' => 2048,
				'messages'   => $messages,
			];
			if ( '' !== trim( $system ) ) {
				$body['system'] = $system;
			}
			if ( ! empty( $tools ) ) {
				$body['tools'] = $tools;
			}

			$resp = self::http_json( self::ANTHROPIC_URL, [
				'x-api-key'         => $api_key,
				'anthropic-version' => self::ANTHROPIC_VERSION,
				'content-type'      => 'application/json',
			], $body );

			if ( isset( $resp['error'] ) ) {
				return [ 'error' => 'Anthropic: ' . $resp['error'] ];
			}

			$content = $resp['content'] ?? [];
			$messages[] = [
				'role' => 'assistant',
				'content' => $content
			];

			if ( ( $resp['stop_reason'] ?? '' ) !== 'tool_use' ) {
				return [
					'reply' => self::text_from_blocks( $content ),
					'steps' => $step,
					'tool_calls' => $tool_calls
				];
			}

			$tool_results = [];
			foreach ( $content as $block ) {
				if ( ( $block['type'] ?? '' ) === 'tool_use' ) {
					$out          = self::run_tool( $block['name'], (array) ( $block['input'] ?? [] ), $ctx );
					$tool_calls[] = [
						'tool' => $block['name'],
						'input' => $block['input'] ?? []
					];
					$tool_results[] = [
						'type'        => 'tool_result',
						'tool_use_id' => $block['id'],
						'content'     => $out,
					];
				}
			}
			$messages[] = [
				'role' => 'user',
				'content' => $tool_results
			];
		}//end for

		return [ 'error' => 'Agent reached the max step limit without finishing.' ];
	}

	private static function text_from_blocks( array $content ): string {
		$out = '';
		foreach ( $content as $b ) {
			if ( ( $b['type'] ?? '' ) === 'text' ) {
				$out .= $b['text'] ?? '';
			}
		}
		return $out;
	}

	private static function run_openai( string $api_key, string $model, string $system, string $task, array $ctx, int $max_steps, bool $json = false ): array {
		$tools = array_map( static fn( $t ) => [
			'type'     => 'function',
			'function' => [
				'name' => $t['name'],
				'description' => $t['description'],
				'parameters' => $t['schema']
			],
		], self::tool_specs( $ctx ) );

		$messages = [];
		if ( '' !== trim( $system ) ) {
			$messages[] = [
				'role' => 'system',
				'content' => $system
			];
		}
		$messages[] = [
			'role' => 'user',
			'content' => $task
		];
		$tool_calls = [];

		for ( $step = 0; $step <= $max_steps; $step++ ) {
			$body = [
				'model' => $model,
				'messages' => $messages,
				'max_tokens' => 2048
			];
			if ( ! empty( $tools ) ) {
				$body['tools'] = $tools;
			}
			// Native JSON mode only when no tools are wired — combining json_object
			// with tool-calling turns is unreliable, so with tools we lean on the
			// system-prompt instruction instead.
			if ( $json && empty( $tools ) ) {
				$body['response_format'] = [ 'type' => 'json_object' ];
			}

			$resp = self::http_json( self::OPENAI_URL, [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			], $body );

			if ( isset( $resp['error'] ) ) {
				return [ 'error' => 'OpenAI: ' . $resp['error'] ];
			}

			$message = $resp['choices'][0]['message'] ?? [];
			$messages[] = $message;

			$calls = $message['tool_calls'] ?? [];
			if ( empty( $calls ) ) {
				return [
					'reply' => (string) ( $message['content'] ?? '' ),
					'steps' => $step,
					'tool_calls' => $tool_calls
				];
			}

			foreach ( $calls as $call ) {
				$name = $call['function']['name'] ?? '';
				$args = json_decode( (string) ( $call['function']['arguments'] ?? '{}' ), true );
				$out  = self::run_tool( $name, is_array( $args ) ? $args : [], $ctx );
				$tool_calls[] = [
					'tool' => $name,
					'input' => $args
				];
				$messages[]   = [
					'role' => 'tool',
					'tool_call_id' => $call['id'] ?? '',
					'content' => $out
				];
			}
		}//end for

		return [ 'error' => 'Agent reached the max step limit without finishing.' ];
	}

	private static function http_json( string $url, array $headers, array $body ): array {
		$resp = wp_remote_post( $url, [
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
			'timeout' => 120,
		] );

		if ( is_wp_error( $resp ) ) {
			return [ 'error' => $resp->get_error_message() ];
		}

		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( isset( $data['error'] ) ) {
			$msg = is_array( $data['error'] ) ? ( $data['error']['message'] ?? 'API error' ) : (string) $data['error'];
			return [ 'error' => $msg ];
		}

		return is_array( $data ) ? $data : [ 'error' => 'Invalid response.' ];
	}

	private static function err( string $message, array $input ): array {
		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'success' => false,
				'error' => $message
			] )
		];
	}
}
