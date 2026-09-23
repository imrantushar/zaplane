<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\AiHttp;

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

	public static function get_category(): string {
		return 'tool';
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
						'label' => 'WordPress AI (uses the provider configured in WordPress)'
					],
					[
						'value' => 'anthropic',
						'label' => 'Anthropic (Claude)'
					],
					[
						'value' => 'openai',
						'label' => 'OpenAI'
					],
					[
						'value' => 'openai_compatible',
						'label' => 'OpenAI-compatible (Azure, OpenRouter, Ollama, local…)'
					],
				],
				'help'     => 'WordPress AI runs the agent on the provider configured in WordPress 7.0+, with no key stored here. Choose another provider to use your own key; the model must support tool calling.',
			],
			'api_key'  => [
				'type'       => 'password',
				'label'      => 'API Key',
				'required'   => false,
				'depends_on' => [ 'provider' => [ 'anthropic', 'openai', 'openai_compatible' ] ],
			],
			'base_url' => [
				'type'        => 'text',
				'label'       => 'Base URL',
				'required'    => false,
				'placeholder' => 'https://api.example.com/v1',
				'depends_on'  => [ 'provider' => [ 'openai_compatible' ] ],
				'help'        => 'For OpenAI-compatible endpoints — the base URL; /chat/completions is appended automatically. The model must support tool/function calling.',
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

		// The agent's Chat Model, Memory, and Tools are wired on the canvas via the
		// node's sub-input handles — so those settings intentionally do NOT appear
		// here. Configure only the agent's own "brain": instructions, task, step
		// budget, and output format. (execute_node still honours any legacy inline
		// model/knowledge/http/mcp config for backward compatibility.)
		return [
			[
				'key'         => 'system_prompt',
				'label'       => 'Agent Instructions',
				'type'        => 'textarea',
				'required'    => false,
				'placeholder' => 'You are a support agent for Acme. Use the connected tools to find answers before replying.',
			],
			[
				'key'         => 'task',
				'label'       => 'Task / Message',
				'type'        => 'expression',
				'required'    => true,
				'placeholder' => '{{trigger.text}}',
			],
			[
				'key'      => 'max_steps',
				'label'    => 'Max Tool Steps',
				'type'     => 'number',
				'required' => false,
				'default'  => self::DEFAULT_MAX_STEPS,
				'help'     => 'How many tool-use rounds the agent may take before it must answer.',
			],
			[
				'key'     => 'response_format',
				'label'   => 'Response Format',
				'type'    => 'select',
				'default' => 'text',
				'options' => [
					[
						'value' => 'text',
						'label' => 'Text'
					],
					[
						'value' => 'json',
						'label' => 'JSON (structured)'
					],
				],
				'help'    => 'Text returns the reply as-is. JSON asks the model for a structured JSON object and parses it into a `json` output field.',
			],
			[
				'key'     => 'persist_memory',
				'label'   => 'Save turn to Memory',
				'type'    => 'select',
				'default' => 'yes',
				'options' => [
					[
						'value' => 'yes',
						'label' => 'Yes — append the question and answer'
					],
					[
						'value' => 'no',
						'label' => 'No'
					],
				],
				'help'    => 'When a Memory node is wired to this agent, automatically save the user message and the reply so the next turn has context (no manual append steps needed).',
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$config      = $node['data']['config'] ?? [];
		$credentials = $node['_connection_credentials'] ?? [];

		$api_key  = $credentials['api_key'] ?? '';
		// Connections saved before the WordPress provider existed carry a key and
		// no provider; they were Anthropic.
		$provider = $credentials['provider'] ?? ( '' !== $api_key ? 'anthropic' : 'wordpress' );

		if ( 'wordpress' !== $provider && '' === $api_key ) {
			return self::err( 'No AI Agent connection credentials available.', $input );
		}

		$task = (string) ( $config['task'] ?? '' );
		if ( '' === trim( $task ) ) {
			return self::err( 'A task/message is required.', $input );
		}

		$ctx = [
			'business_key' => trim( (string) ( $config['business_key'] ?? '' ) ),
			'enable_http'  => ( 'yes' === ( $config['enable_http'] ?? 'no' ) ),
			'mcp_server'   => trim( (string) ( $config['mcp_server_url'] ?? '' ) ),
			'mcp_token'    => trim( (string) ( $config['mcp_auth_token'] ?? '' ) ),
			'mcp_tools'    => [],
			'mcp_name_map' => [],
		];

		// Discover MCP tools once so they can be offered to the model and routed
		// back on call. Any tool the connected MCP server exposes becomes callable.
		if ( '' !== $ctx['mcp_server'] ) {
			$mcp_seen = [];
			foreach ( self::mcp_list_tools( $ctx['mcp_server'], $ctx['mcp_token'] ) as $tool ) {
				$name = (string) ( $tool['name'] ?? '' );
				if ( '' === $name ) {
					continue;
				}
				$agent_name = 'mcp_' . preg_replace( '/[^a-zA-Z0-9_-]/', '_', $name );
				// Keep tool names unique — providers reject duplicates.
				$base = $agent_name;
				$j    = 2;
				while ( isset( $mcp_seen[ $agent_name ] ) ) {
					$agent_name = $base . '_' . $j++;
				}
				$mcp_seen[ $agent_name ] = true;

				$ctx['mcp_tools'][] = [
					'name'        => $agent_name,
					'description' => (string) ( $tool['description'] ?? ( 'MCP tool: ' . $name ) ),
					'schema'      => ( isset( $tool['inputSchema'] ) && is_array( $tool['inputSchema'] ) )
						? $tool['inputSchema']
						: [
							'type' => 'object',
							'properties' => (object) []
						],
				];
				$ctx['mcp_name_map'][ $agent_name ] = $name;
			}//end foreach
		}//end if

		// Sub-nodes wired into the agent on the canvas (Tools / Memory / Chat
		// Model). Any connected action node becomes a callable tool; a memory node
		// supplies history; a chat-model node overrides the model.
		$sub               = $node['_sub_nodes'] ?? [];
		$ctx['tool_nodes'] = self::build_tool_nodes( $sub['ai_tool'] ?? [] );
		$history           = empty( $sub['ai_memory'] ) ? [] : self::load_memory_history( $sub['ai_memory'] );

		$model     = sanitize_text_field( (string) ( $config['model'] ?? self::DEFAULT_MODEL ) );
		if ( ! empty( $sub['ai_model']['data']['config']['model'] ) ) {
			$model = sanitize_text_field( (string) $sub['ai_model']['data']['config']['model'] );
		}
		$system    = (string) ( $config['system_prompt'] ?? '' );
		$max_steps = max( 1, (int) ( $config['max_steps'] ?? self::DEFAULT_MAX_STEPS ) );
		$format    = ( 'json' === ( $config['response_format'] ?? 'text' ) ) ? 'json' : 'text';

		// Nudge the model toward pure JSON on top of any provider-native setting.
		if ( 'json' === $format ) {
			$system = trim( $system . "\n\nReturn ONLY a single valid JSON object as your final answer — no prose, no markdown code fences." );
		}

		if ( 'wordpress' === $provider ) {
			$result = self::run_wordpress( $system, $task, $ctx, $max_steps, 'json' === $format, $history );
			$model  = $result['model'] ?? $model;
		} elseif ( 'openai' === $provider ) {
			$result = self::run_openai( $api_key, $model, $system, $task, $ctx, $max_steps, 'json' === $format, $history );
		} elseif ( 'openai_compatible' === $provider ) {
			$base     = trim( (string) ( $credentials['base_url'] ?? '' ) );
			if ( '' === $base ) {
				return self::err( 'A Base URL is required for an OpenAI-compatible connection.', $input );
			}
			$endpoint = rtrim( $base, '/' ) . '/chat/completions';
			$result   = self::run_openai( $api_key, $model, $system, $task, $ctx, $max_steps, 'json' === $format, $history, $endpoint );
		} else {
			$result = self::run_anthropic( $api_key, $model, $system, $task, $ctx, $max_steps, $history );
		}

		if ( isset( $result['error'] ) ) {
			return self::err( $result['error'], $input );
		}

		// Auto-persist the turn to a wired Memory node so the next run has context,
		// removing the need for manual append steps. Off when persist_memory=no.
		if ( ! empty( $sub['ai_memory'] ) && 'no' !== ( $config['persist_memory'] ?? 'yes' ) ) {
			self::persist_turn( $sub['ai_memory'], $task, (string) $result['reply'] );
		}

		$data = array_merge( $input, [
			'success'    => true,
			'reply'      => $result['reply'],
			'steps'      => $result['steps'],
			'tool_calls' => $result['tool_calls'],
			'usage'      => $result['usage'] ?? self::empty_usage(),
			'cost_usd'   => AiHttp::estimate_cost( $model, $result['usage'] ?? self::empty_usage() ),
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

		// MCP server tools, discovered in execute_node.
		foreach ( $ctx['mcp_tools'] ?? [] as $tool ) {
			$tools[] = $tool;
		}

		// Tool sub-nodes wired into the agent on the canvas.
		foreach ( $ctx['tool_nodes'] ?? [] as $tool ) {
			$tools[] = $tool['spec'];
		}

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
			$resp   = \Zaplane\HttpGuard::request( $url, [
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

		// MCP tool — route back to the connected MCP server.
		if ( isset( $ctx['mcp_name_map'][ $name ] ) ) {
			return self::mcp_call_tool( $ctx['mcp_server'], $ctx['mcp_token'], $ctx['mcp_name_map'][ $name ], $args );
		}

		// Tool sub-node — run the connected action node with the model's args.
		foreach ( $ctx['tool_nodes'] ?? [] as $tool ) {
			if ( $tool['agent_name'] === $name ) {
				return self::run_tool_node( $tool, $args );
			}
		}

		return 'Unknown tool: ' . $name;
	}

	/**
	 * Turn nodes wired into the agent's Tools handle into callable tool specs.
	 * Each connected action node's config schema becomes the tool's parameters.
	 *
	 * @param array<int,array<string,mixed>> $nodes
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_tool_nodes( array $nodes ): array {
		if ( ! class_exists( '\Zaplane\Framework\Core\IntegrationLoader' ) ) {
			return [];
		}

		$tools = [];
		$seen  = [];
		foreach ( $nodes as $n ) {
			$app   = strtolower( (string) ( $n['data']['app'] ?? '' ) );
			$event = (string) ( $n['data']['event'] ?? '' );
			if ( '' === $app || '' === $event ) {
				continue;
			}

			$instance = \Zaplane\Framework\Core\IntegrationLoader::get( $app );
			if ( ! $instance ) {
				continue;
			}
			$class  = get_class( $instance );
			$schema = method_exists( $class, 'get_action_config_schema' ) ? $class::get_action_config_schema( $event ) : [];

			$base = (string) ( $n['data']['name'] ?? ( $app . '_' . $event ) );
			$name = 'tool_' . preg_replace( '/[^a-zA-Z0-9_-]/', '_', $base );
			// Keep tool names unique across identically-named nodes.
			$unique = $name;
			$i      = 2;
			while ( isset( $seen[ $unique ] ) ) {
				$unique = $name . '_' . $i++;
			}
			$seen[ $unique ] = true;

			$tools[] = [
				'agent_name'   => $unique,
				'event'        => $event,
				'class'        => $class,
				'saved_config' => is_array( $n['data']['config'] ?? null ) ? $n['data']['config'] : [],
				'spec'         => [
					'name'        => $unique,
					'description' => sprintf( 'Run the %s "%s" action and return its output.', $app, $event ),
					'schema'      => self::schema_to_json_schema( $schema ),
				],
			];
		}//end foreach

		return $tools;
	}

	/**
	 * Map a Zaplane action config schema to a JSON Schema for tool parameters.
	 *
	 * @param array<int,array<string,mixed>> $schema
	 * @return array<string,mixed>
	 */
	private static function schema_to_json_schema( array $schema ): array {
		$props    = [];
		$required = [];
		foreach ( $schema as $f ) {
			$key = (string) ( $f['key'] ?? '' );
			if ( '' === $key ) {
				continue;
			}
			$type = $f['type'] ?? 'text';
			$json = 'number' === $type ? 'number' : ( in_array( $type, [ 'checkbox', 'boolean' ], true ) ? 'boolean' : 'string' );

			$props[ $key ] = [ 'type' => $json ];
			if ( ! empty( $f['label'] ) ) {
				$props[ $key ]['description'] = (string) $f['label'];
			}
			if ( ! empty( $f['required'] ) ) {
				$required[] = $key;
			}
		}

		$out = [
			'type' => 'object',
			'properties' => empty( $props ) ? (object) [] : $props
		];
		if ( ! empty( $required ) ) {
			$out['required'] = $required;
		}
		return $out;
	}

	/**
	 * Execute a connected tool node with the model-supplied arguments merged over
	 * the node's saved config, and return its output as a string.
	 *
	 * @param array<string,mixed> $tool
	 * @param array<string,mixed> $args
	 */
	private static function run_tool_node( array $tool, array $args ): string {
		$config = array_merge( $tool['saved_config'], is_array( $args ) ? $args : [] );
		try {
			$out = $tool['class']::execute_node(
				[
					'data' => [
						'event' => $tool['event'],
						'config' => $config
					]
				],
				[]
			);
		} catch ( \Throwable $e ) {
			return 'Tool error: ' . $e->getMessage();
		}

		$data = $out['data'] ?? $out;
		return is_scalar( $data ) ? (string) $data : (string) wp_json_encode( $data );
	}

	/**
	 * Merge consecutive same-role text messages so the transcript strictly
	 * alternates — Anthropic rejects two user (or two assistant) turns in a row.
	 *
	 * @param array<int,array{role:string,content:mixed}> $messages
	 * @return array<int,array{role:string,content:mixed}>
	 */
	private static function normalize_roles( array $messages ): array {
		$out = [];
		foreach ( $messages as $m ) {
			$n = count( $out );
			if ( $n > 0 && $out[ $n - 1 ]['role'] === $m['role']
				&& is_string( $out[ $n - 1 ]['content'] ) && is_string( $m['content'] ) ) {
				$out[ $n - 1 ]['content'] = trim( $out[ $n - 1 ]['content'] . "\n\n" . $m['content'] );
				continue;
			}
			$out[] = $m;
		}
		return $out;
	}

	/**
	 * Load prior turns from a connected Memory sub-node as chat messages.
	 *
	 * @param array<string,mixed> $memory_node
	 * @return array<int,array{role:string,content:string}>
	 */
	private static function load_memory_history( array $memory_node ): array {
		if ( ! class_exists( '\Zaplane\Integrations\Memory' ) ) {
			return [];
		}
		$config = is_array( $memory_node['data']['config'] ?? null ) ? $memory_node['data']['config'] : [];
		$out     = \Zaplane\Integrations\Memory::execute_node(
			[
				'data' => [
					'event' => 'get_history',
					'config' => $config
				]
			],
			[]
		);
		$history = $out['data']['history'] ?? [];

		$messages = [];
		if ( is_array( $history ) ) {
			foreach ( $history as $turn ) {
				if ( ! is_array( $turn ) ) {
					continue;
				}
				$content = (string) ( $turn['content'] ?? '' );
				if ( '' === $content ) {
					continue;
				}
				$messages[] = [
					'role'    => ( 'assistant' === ( $turn['role'] ?? '' ) ) ? 'assistant' : 'user',
					'content' => $content,
				];
			}
		}

		return self::trim_history( $messages );
	}

	/**
	 * Bound the loaded history to a character budget so long-running conversations
	 * don't blow the context window (or the bill). Keeps the most recent turns.
	 *
	 * @param array<int,array{role:string,content:string}> $messages
	 * @return array<int,array{role:string,content:string}>
	 */
	private static function trim_history( array $messages ): array {
		/**
		 * Filter the history budget (characters). ~4 chars/token, so 12000 chars
		 * is roughly 3k tokens of history.
		 */
		$budget = (int) apply_filters( 'zaplane_agent_history_char_budget', 12000 );
		if ( $budget <= 0 ) {
			return $messages;
		}

		$kept  = [];
		$total = 0;
		// Walk newest→oldest, keeping turns until the budget is spent.
		for ( $i = count( $messages ) - 1; $i >= 0; $i-- ) {
			$len = strlen( $messages[ $i ]['content'] );
			if ( $total + $len > $budget && ! empty( $kept ) ) {
				break;
			}
			$total += $len;
			array_unshift( $kept, $messages[ $i ] );
		}
		return $kept;
	}

	/**
	 * Append the user message and the assistant reply to a wired Memory node.
	 *
	 * @param array<string,mixed> $memory_node
	 */
	private static function persist_turn( array $memory_node, string $user, string $assistant ): void {
		if ( ! class_exists( '\Zaplane\Integrations\Memory' ) ) {
			return;
		}
		$config = is_array( $memory_node['data']['config'] ?? null ) ? $memory_node['data']['config'] : [];

		foreach ( [ [ 'user', $user ], [ 'assistant', $assistant ] ] as $turn ) {
			if ( '' === trim( (string) $turn[1] ) ) {
				continue;
			}
			\Zaplane\Integrations\Memory::execute_node(
				[
					'data' => [
						'event'  => 'append',
						'config' => array_merge(
							$config,
							[
								'role'    => $turn[0],
								'content' => $turn[1],
							]
						),
					],
				],
				[]
			);
		}
	}

	/**
	 * List the tools a connected MCP server exposes.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function mcp_list_tools( string $server, string $token ): array {
		if ( ! class_exists( '\Zaplane\Integrations\Mcpclient' ) ) {
			return [];
		}
		$res = \Zaplane\Integrations\Mcpclient::execute_node(
			[
				'data' => [
					'event' => 'list_tools',
					'config' => [
						'server_url' => $server,
						'auth_token' => $token
					]
				]
			],
			[]
		);
		$tools = $res['data']['tools'] ?? [];
		return is_array( $tools ) ? $tools : [];
	}

	/**
	 * Call one tool on the connected MCP server and return its text result.
	 *
	 * @param array<string,mixed> $args
	 */
	private static function mcp_call_tool( string $server, string $token, string $tool, array $args ): string {
		if ( ! class_exists( '\Zaplane\Integrations\Mcpclient' ) ) {
			return 'MCP client unavailable.';
		}
		$res = \Zaplane\Integrations\Mcpclient::execute_node(
			[
				'data' => [
					'event'  => 'call_tool',
					'config' => [
						'server_url' => $server,
						'auth_token' => $token,
						'tool_name'  => $tool,
						'arguments'  => wp_json_encode( $args ),
					],
				],
			],
			[]
		);
		$data = $res['data'] ?? [];
		if ( empty( $data['success'] ) && ! empty( $data['error'] ) ) {
			return 'MCP error: ' . $data['error'];
		}
		return (string) ( $data['result'] ?? '' );
	}

	private static function run_anthropic( string $api_key, string $model, string $system, string $task, array $ctx, int $max_steps, array $history = [] ): array {
		$tools = array_map( static fn( $t ) => [
			'name'         => $t['name'],
			'description'  => $t['description'],
			'input_schema' => $t['schema'],
		], self::tool_specs( $ctx ) );

		// Cache the tool definitions (stable across every step of the loop) by
		// marking the end of the tools block — Anthropic caches the whole prefix.
		if ( ! empty( $tools ) ) {
			$last                        = count( $tools ) - 1;
			$tools[ $last ]['cache_control'] = [ 'type' => 'ephemeral' ];
		}

		$messages   = self::normalize_roles(
			array_merge(
				$history,
				[
					[
						'role'    => 'user',
						'content' => $task,
					],
				]
			)
		);
		$tool_calls = [];
		$usage      = self::empty_usage();

		for ( $step = 0; $step <= $max_steps; $step++ ) {
			$body = [
				'model'      => $model,
				'max_tokens' => 2048,
				'messages'   => $messages,
			];
			if ( '' !== trim( $system ) ) {
				$body['system'] = AiHttp::anthropic_cached_text( $system );
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

			$usage = self::add_usage( $usage, AiHttp::usage( 'anthropic', $resp ) );

			$content = $resp['content'] ?? [];
			$messages[] = [
				'role' => 'assistant',
				'content' => $content
			];

			if ( ( $resp['stop_reason'] ?? '' ) !== 'tool_use' ) {
				return [
					'reply' => self::text_from_blocks( $content ),
					'steps' => $step,
					'tool_calls' => $tool_calls,
					'usage' => $usage,
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

	/**
	 * Run the tool loop through the AI Client in WordPress core (7.0+), on the
	 * provider the site owner configured in WordPress.
	 *
	 * @param array<int,array{role:string,content:mixed}> $history
	 */
	private static function run_wordpress( string $system, string $task, array $ctx, int $max_steps, bool $json = false, array $history = [] ): array {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return [ 'error' => 'WordPress AI is unavailable (requires WordPress 7.0+). Choose another provider on this connection.' ];
		}
		if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
			return [ 'error' => 'AI features are disabled in this WordPress environment.' ];
		}

		$declarations = [];
		foreach ( self::tool_specs( $ctx ) as $t ) {
			$declarations[] = new \WordPress\AiClient\Tools\DTO\FunctionDeclaration( $t['name'], $t['description'], $t['schema'] );
		}

		$messages = [];
		foreach ( self::normalize_roles( array_merge( $history, [ [ 'role' => 'user', 'content' => $task ] ] ) ) as $turn ) {
			$text = is_string( $turn['content'] ?? null ) ? $turn['content'] : (string) wp_json_encode( $turn['content'] ?? '' );
			if ( '' === trim( $text ) ) {
				continue;
			}
			// The AI Client insists the conversation opens with the user.
			if ( empty( $messages ) && 'assistant' === ( $turn['role'] ?? '' ) ) {
				continue;
			}
			$part       = new \WordPress\AiClient\Messages\DTO\MessagePart( $text );
			$messages[] = 'assistant' === ( $turn['role'] ?? '' )
				? new \WordPress\AiClient\Messages\DTO\ModelMessage( [ $part ] )
				: new \WordPress\AiClient\Messages\DTO\UserMessage( [ $part ] );
		}

		$tool_calls = [];
		$usage      = self::empty_usage();

		for ( $step = 0; $step <= $max_steps; $step++ ) {
			$builder = wp_ai_client_prompt( $messages )->using_max_tokens( 2048 );
			if ( '' !== trim( $system ) ) {
				$builder->using_system_instruction( $system );
			}
			if ( ! empty( $declarations ) ) {
				$builder->using_function_declarations( ...$declarations );
			}

			$result = $builder->generate_text_result();
			if ( is_wp_error( $result ) ) {
				return [ 'error' => 'WordPress AI: ' . $result->get_error_message() ];
			}

			$tokens = $result->getTokenUsage();
			$usage  = self::add_usage( $usage, [
				'input_tokens'  => $tokens->getPromptTokens(),
				'output_tokens' => $tokens->getCompletionTokens(),
				'total_tokens'  => $tokens->getTotalTokens(),
			] );

			$message    = $result->toMessage();
			$messages[] = $message;

			$calls = [];
			$reply = '';
			foreach ( $message->getParts() as $part ) {
				if ( null !== $part->getFunctionCall() ) {
					$calls[] = $part->getFunctionCall();
				} elseif ( null !== $part->getText() && $part->getChannel()->isContent() ) {
					$reply .= $part->getText();
				}
			}

			if ( empty( $calls ) ) {
				return [
					'reply'      => $reply,
					'steps'      => $step,
					'tool_calls' => $tool_calls,
					'usage'      => $usage,
					'model'      => $result->getModelMetadata()->getId(),
				];
			}

			// One message per result: some providers refuse a function response
			// that shares its message with anything else.
			foreach ( $calls as $call ) {
				$name = (string) $call->getName();
				$args = $call->getArgs();
				if ( is_string( $args ) ) {
					$args = json_decode( $args, true );
				}
				$args         = is_array( $args ) ? $args : [];
				$out          = self::run_tool( $name, $args, $ctx );
				$tool_calls[] = [
					'tool'  => $name,
					'input' => $args,
				];
				$messages[]   = new \WordPress\AiClient\Messages\DTO\UserMessage( [
					new \WordPress\AiClient\Messages\DTO\MessagePart(
						new \WordPress\AiClient\Tools\DTO\FunctionResponse( $call->getId(), $name, $out )
					),
				] );
			}
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

	private static function run_openai( string $api_key, string $model, string $system, string $task, array $ctx, int $max_steps, bool $json = false, array $history = [], string $endpoint = self::OPENAI_URL ): array {
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
		foreach ( $history as $turn ) {
			$messages[] = $turn;
		}
		$messages[] = [
			'role' => 'user',
			'content' => $task
		];
		$tool_calls = [];
		$usage      = self::empty_usage();

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

			$resp = self::http_json( $endpoint, [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			], $body );

			if ( isset( $resp['error'] ) ) {
				return [ 'error' => 'OpenAI: ' . $resp['error'] ];
			}

			$usage = self::add_usage( $usage, AiHttp::usage( 'openai', $resp ) );

			$message = $resp['choices'][0]['message'] ?? [];
			$messages[] = $message;

			$calls = $message['tool_calls'] ?? [];
			if ( empty( $calls ) ) {
				return [
					'reply' => (string) ( $message['content'] ?? '' ),
					'steps' => $step,
					'tool_calls' => $tool_calls,
					'usage' => $usage,
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
		// Retries 429/5xx with backoff so a transient rate limit mid-agent-loop
		// doesn't kill the whole run.
		$res = AiHttp::request( $url, $headers, $body );

		if ( null !== $res['error'] ) {
			return [ 'error' => $res['error'] ];
		}

		return is_array( $res['data'] ) && ! empty( $res['data'] ) ? $res['data'] : [ 'error' => 'Invalid response.' ];
	}

	/**
	 * @return array{input_tokens:int,output_tokens:int,total_tokens:int}
	 */
	private static function empty_usage(): array {
		return [
			'input_tokens'  => 0,
			'output_tokens' => 0,
			'total_tokens'  => 0,
		];
	}

	/**
	 * Sum token usage across agent steps.
	 *
	 * @param array{input_tokens:int,output_tokens:int,total_tokens:int} $acc
	 * @param array{input_tokens:int,output_tokens:int,total_tokens:int} $add
	 * @return array{input_tokens:int,output_tokens:int,total_tokens:int}
	 */
	private static function add_usage( array $acc, array $add ): array {
		return [
			'input_tokens'  => $acc['input_tokens'] + $add['input_tokens'],
			'output_tokens' => $acc['output_tokens'] + $add['output_tokens'],
			'total_tokens'  => $acc['total_tokens'] + $add['total_tokens'],
		];
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
