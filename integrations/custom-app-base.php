<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\CustomApps\ManifestStore;
use Zaplane\CustomApps\Template;
use Zaplane\CustomApps\RequestBuilder;
use Zaplane\CustomApps\ResponseMapper;
use Zaplane\CustomApps\HttpClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class CustomAppBase extends IntegrationBase {

	protected static string $slug = '';

	public static function get_slug(): string {
		return static::$slug;
	}

	/**
	 * Pin a pooled slot class to the custom app it stands for.
	 *
	 * Every slot redeclares $slug, so this writes that slot's own copy, and only
	 * once: a slot does not change apps within a request.
	 */
	public static function bind_slug( string $slug ): void {
		if ( self::class !== static::class && '' === static::$slug ) {
			static::$slug = $slug;
		}
	}

	protected static function manifest(): array {
		$manifest = ManifestStore::get( static::get_slug() );
		return is_array( $manifest ) ? $manifest : [];
	}

	protected static function kind(): string {
		$kind = (string) ( self::manifest()['kind'] ?? 'http' );
		return 'local' === $kind ? 'local' : 'http';
	}

	public static function get_name(): string {
		$manifest = self::manifest();
		return ! empty( $manifest['name'] ) ? (string) $manifest['name'] : ucfirst( static::get_slug() );
	}

	public static function get_icon(): string {
		return (string) ( self::manifest()['icon'] ?? '' );
	}

	public static function get_category(): string {
		return (string) ( self::manifest()['category'] ?? 'app' );
	}

	public static function get_actions(): array {
		return self::index_events( self::manifest()['actions'] ?? [] );
	}

	public static function get_action_config_schema( string $action ): array {
		$def = self::find_event( self::manifest()['actions'] ?? [], $action );
		return is_array( $def['fields'] ?? null ) ? $def['fields'] : [];
	}

	/**
	 * Sample output for the "@" picker, built from the action's declared output
	 * mappings so downstream nodes can reference them before any test run.
	 */
	public static function get_action_sample_output( string $action ): array {
		$def    = self::find_event( self::manifest()['actions'] ?? [], $action );
		$sample = [];

		foreach ( (array) ( $def['output'] ?? [] ) as $output ) {
			if ( is_array( $output ) && ! empty( $output['key'] ) ) {
				$sample[ (string) $output['key'] ] = '';
			}
		}

		$sample['success'] = true;
		$sample['status']  = 200;

		return $sample;
	}

	public static function get_triggers(): array {
		$triggers = self::manifest()['triggers'] ?? [];
		if ( ! is_array( $triggers ) ) {
			return [];
		}

		$is_local = 'local' === self::kind();

		$indexed = [];
		foreach ( $triggers as $trigger ) {
			if ( ! is_array( $trigger ) || empty( $trigger['key'] ) ) {
				continue;
			}
			$key = (string) $trigger['key'];

			// Local apps listen on a REAL WordPress hook (fired by the other
			// plugin); HTTP apps use a synthetic hook that the poller / webhook
			// controller dispatch to. Either way Query::resolve_hook() reads it.
			$hook = $is_local ? (string) ( $trigger['hook'] ?? '' ) : self::trigger_hook( $key );
			if ( '' === $hook ) {
				continue;
			}

			$label = (string) ( $trigger['label'] ?? '' );

			$indexed[ $key ] = [
				// Fall back to the key when no label was authored — an empty label
				// renders as a blank, unselectable option in the node's type dropdown.
				'label' => '' !== $label ? $label : $key,
				'hook'  => $hook,
			];
		}//end foreach
		return $indexed;
	}

	public static function trigger_hook( string $key ): string {
		return 'zaplane_ca_' . static::get_slug() . '_' . $key;
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		$def = self::find_event( self::manifest()['triggers'] ?? [], $trigger );
		return is_array( $def['fields'] ?? null ) ? $def['fields'] : [];
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		$def = self::find_event( self::manifest()['triggers'] ?? [], $trigger );

		// An explicitly authored sample wins.
		if ( ! empty( $def['sample_output'] ) && is_array( $def['sample_output'] ) ) {
			return $def['sample_output'];
		}

		// Otherwise synthesise a sample from the trigger's declared output fields
		// (HTTP/webhook apps) or hook arg names (local apps). Without this the "@"
		// data picker has no keys to offer for a custom-app trigger until a real
		// test run captures a payload — the authoring UI stores `fields`/`args`,
		// never a `sample_output` key. Mirrors get_action_sample_output().
		$sample = [];

		foreach ( (array) ( $def['fields'] ?? [] ) as $field ) {
			if ( is_array( $field ) && ! empty( $field['key'] ) ) {
				$sample[ (string) $field['key'] ] = '';
			}
		}

		foreach ( (array) ( $def['args'] ?? [] ) as $arg ) {
			$name = is_string( $arg )
				? $arg
				: ( is_array( $arg ) ? (string) ( $arg['key'] ?? $arg['name'] ?? '' ) : '' );
			if ( '' !== $name ) {
				$sample[ $name ] = '';
			}
		}

		return $sample;
	}

	public static function resolve_trigger( array $node, array $hook_args ) {
		if ( 'local' === self::kind() ) {
			return self::resolve_local_trigger( $node, $hook_args );
		}

		// Webhook/polling payloads are delivered pre-shaped as the first hook arg.
		$payload = $hook_args[0] ?? false;
		return is_array( $payload ) ? $payload : false;
	}

	protected static function resolve_local_trigger( array $node, array $hook_args ) {
		$event   = (string) ( $node['event'] ?? '' );
		$trigger = self::find_event( self::manifest()['triggers'] ?? [], $event );
		if ( empty( $trigger ) ) {
			return false;
		}

		$names = is_array( $trigger['args'] ?? null ) ? $trigger['args'] : [];

		$payload = [];
		foreach ( $names as $index => $name ) {
			$payload[ (string) $name ] = self::normalize_arg( $hook_args[ $index ] ?? null );
		}

		// With no declared args, expose the first arg as the whole payload.
		if ( empty( $names ) ) {
			$first = self::normalize_arg( $hook_args[0] ?? null );
			return is_array( $first ) ? $first : [ 'value' => $first ];
		}

		return $payload;
	}

	protected static function execute_local_action( array $action, string $event, array $config, array $credentials ): array {
		$handler = is_array( $action['handler'] ?? null ) ? $action['handler'] : [];
		$type    = (string) ( $handler['type'] ?? '' );

		if ( empty( $action ) || '' === $type ) {
			throw new \Exception( 'Unknown local action "' . esc_html( $event ) . '" for custom app "' . esc_html( static::get_slug() ) . '".' );
		}

		$context   = Template::build_context( $config, $credentials );
		$arg_specs = is_array( $handler['args'] ?? null ) ? $handler['args'] : [];
		$args      = array_map(
			static function ( $spec ) use ( $context ) {
				return Template::interpolate( $spec, $context );
			},
			array_values( $arg_specs )
		);

		$result = null;

		if ( 'do_action' === $type ) {
			$hook = (string) ( $handler['name'] ?? '' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Manifest-configured hook, admin-authored.
			do_action_ref_array( $hook, $args );
		} elseif ( 'apply_filters' === $type ) {
			$hook   = (string) ( $handler['name'] ?? '' );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Manifest-configured hook, admin-authored.
			$result = apply_filters_ref_array( $hook, $args );
		} else { // function
			$callable = (string) ( $handler['callable'] ?? '' );

			// Fail gracefully instead of ever emitting a fatal: verify the target
			// exists and isn't a known-dangerous function before invoking it.
			$reason = self::callable_error( $callable );
			if ( null !== $reason ) {
				return [
					'port' => 'main',
					'data' => [
						'success' => false,
						'error'   => $reason,
					],
				];
			}

			$result = call_user_func_array( $callable, $args );
		}//end if

		$outputs = ResponseMapper::map_outputs( self::normalize_arg( $result ), is_array( $action['output'] ?? null ) ? $action['output'] : [] );

		return [
			'port' => 'main',
			'data' => array_merge( $outputs, [ 'success' => true ] ),
		];
	}

	protected const BLOCKED_CALLABLES = [
		'eval',
		'assert',
		'exec',
		'system',
		'passthru',
		'shell_exec',
		'proc_open',
		'popen',
		'pcntl_exec',
		'proc_close',
		'create_function',
		'call_user_func',
		'call_user_func_array',
		'array_map',
		'array_walk',
		'array_filter',
		'array_reduce',
		'register_shutdown_function',
		'unlink',
		'file_put_contents',
		'fwrite',
		'fputs',
		'fopen',
		'move_uploaded_file',
		'rename',
		'copy',
	];

	protected static function callable_error( string $callable ): ?string {
		if ( '' === $callable ) {
			return 'No function name was provided.';
		}

		/**
		 * PHP functions a local Custom App may call.
		 *
		 * A manifest is data saved from an admin screen, and a function named there
		 * runs with whatever arguments the workflow resolves — for a webhook trigger,
		 * values a stranger posted. So nothing is callable by being typed in: a
		 * function runs only after code on this site has listed it here.
		 *
		 * @param string[] $callables Function names, or "Class::method" strings.
		 * @param string   $slug      The custom app asking.
		 */
		$allowed = array_map(
			'strtolower',
			array_map( 'strval', (array) apply_filters( 'zaplane_custom_app_callables', [], static::get_slug() ) )
		);
		if ( ! in_array( strtolower( $callable ), $allowed, true ) ) {
			return sprintf( 'The function "%s" has not been allowed. Site code can allow it with the zaplane_custom_app_callables filter.', $callable );
		}

		$blocked = array_map(
			'strtolower',
			(array) apply_filters( 'zaplane_customapp_local_blocked_callables', self::BLOCKED_CALLABLES, static::get_slug() )
		);
		if ( in_array( strtolower( $callable ), $blocked, true ) ) {
			return sprintf( 'The function "%s" is blocked for security reasons.', $callable );
		}

		if ( ! is_callable( $callable ) ) {
			return sprintf( 'The function "%s" does not exist or is not callable.', $callable );
		}

		return null;
	}

	protected static function normalize_arg( $value ) {
		if ( is_scalar( $value ) || null === $value || is_array( $value ) ) {
			return $value;
		}
		$encoded = wp_json_encode( $value );
		$decoded = false !== $encoded ? json_decode( $encoded, true ) : null;
		return null !== $decoded ? $decoded : null;
	}

	public static function supports_webhook(): bool {
		foreach ( self::manifest()['triggers'] ?? [] as $trigger ) {
			if ( is_array( $trigger ) && 'webhook' === ( $trigger['mode'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		// Per-trigger verification is enforced in parse_webhook_event() (a request
		// may target any of several webhook triggers, each with its own rule), so
		// the gateway check here only rejects when nothing can ever match.
		return self::supports_webhook();
	}

	public static function parse_webhook_event( \WP_REST_Request $request ): ?array {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		$body = is_array( $body ) ? $body : [];

		foreach ( self::manifest()['triggers'] ?? [] as $trigger ) {
			if ( ! is_array( $trigger ) || 'webhook' !== ( $trigger['mode'] ?? '' ) ) {
				continue;
			}

			$webhook = is_array( $trigger['webhook'] ?? null ) ? $trigger['webhook'] : [];

			if ( ! self::verify_webhook_rule( $webhook, $request ) ) {
				continue;
			}

			// Optional event discriminator: a body path must equal a fixed value.
			if ( ! empty( $webhook['event_path'] ) && isset( $webhook['match'] ) ) {
				$value = ResponseMapper::extract( $body, (string) $webhook['event_path'] );
				if ( (string) $value !== (string) $webhook['match'] ) {
					continue;
				}
			}

			$payload = ! empty( $webhook['items_path'] )
				? ResponseMapper::extract( $body, (string) $webhook['items_path'] )
				: $body;

			return [
				'event'   => (string) $trigger['key'],
				'payload' => is_array( $payload ) ? $payload : [ 'value' => $payload ],
			];
		}//end foreach

		return null;
	}

	protected static function verify_webhook_rule( array $webhook, \WP_REST_Request $request ): bool {
		$verify = is_array( $webhook['verify'] ?? null ) ? $webhook['verify'] : [];
		$type   = (string) ( $verify['type'] ?? 'none' );

		if ( 'none' === $type || '' === $type ) {
			return true;
		}

		$secret = (string) get_option( 'zaplane_ca_webhook_secret_' . static::get_slug(), '' );
		if ( '' === $secret ) {
			return true; // Nothing to verify against yet.
		}

		$header = (string) ( $verify['header'] ?? '' );
		$sent   = '' !== $header ? (string) $request->get_header( $header ) : '';

		if ( 'token' === $type ) {
			return '' !== $sent && hash_equals( $secret, $sent );
		}

		if ( 'hmac' === $type ) {
			$algo     = (string) ( $verify['algo'] ?? 'sha256' );
			$expected = hash_hmac( $algo, (string) $request->get_body(), $secret );
			return '' !== $sent && hash_equals( $expected, $sent );
		}

		return false;
	}

	public static function get_dynamic_queries(): array {
		$queries = self::manifest()['queries'] ?? [];
		if ( ! is_array( $queries ) ) {
			return [];
		}

		$slug = static::get_slug();
		$map  = [];

		foreach ( $queries as $query ) {
			if ( ! is_array( $query ) || empty( $query['key'] ) ) {
				continue;
			}
			$key           = (string) $query['key'];
			$map[ $key ]   = static function ( $params ) use ( $slug, $key ) {
				$integration = \Zaplane\Framework\Core\IntegrationLoader::get( $slug );
				return $integration ? $integration::run_query( $key, is_array( $params ) ? $params : [] ) : [];
			};
		}

		return $map;
	}

	public static function run_query( string $key, array $params ): array {
		$def = self::find_event( self::manifest()['queries'] ?? [], $key );
		if ( empty( $def ) || empty( $def['request'] ) ) {
			return [];
		}

		$credentials = self::credentials_from_params( $params );
		$config      = isset( $params['config'] ) && is_array( $params['config'] ) ? $params['config'] : [];
		$context     = Template::build_context( $config, $credentials );

		$request  = RequestBuilder::build( $def['request'], self::manifest(), $context );
		$response = HttpClient::request( $request['method'], $request['url'], $request['headers'], $request['body'] );

		if ( ! empty( $response['error'] ) ) {
			return [];
		}

		$items    = ResponseMapper::extract_items( $response['body'], (string) ( $def['items_path'] ?? '' ) );
		$map      = is_array( $def['map'] ?? null ) ? $def['map'] : [];
		$value_at = (string) ( $map['value'] ?? 'id' );
		$label_at = (string) ( $map['label'] ?? 'name' );

		$options = [];
		foreach ( $items as $item ) {
			$options[] = [
				'value' => ResponseMapper::extract( $item, $value_at ),
				'label' => (string) ResponseMapper::extract( $item, $label_at ),
			];
		}
		return $options;
	}

	protected static function credentials_from_params( array $params ): array {
		$connection_id = (int) ( $params['connection_id'] ?? 0 );
		if ( $connection_id <= 0 ) {
			return [];
		}
		try {
			return ( new \Zaplane\Framework\Classes\ConnectionManager() )->get_execution_credentials( $connection_id );
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	public static function execute_node( array $node, array $input ): array {
		$manifest = self::manifest();
		if ( empty( $manifest ) ) {
			throw new \Exception( 'Custom app "' . esc_html( static::get_slug() ) . '" is no longer available.' );
		}

		$event  = (string) ( $node['data']['event'] ?? '' );
		$action = self::find_event( $manifest['actions'] ?? [], $event );

		$config      = isset( $node['data']['config'] ) && is_array( $node['data']['config'] ) ? $node['data']['config'] : [];
		$credentials = isset( $node['_connection_credentials'] ) && is_array( $node['_connection_credentials'] ) ? $node['_connection_credentials'] : [];

		if ( 'local' === self::kind() ) {
			return self::execute_local_action( $action, $event, $config, $credentials );
		}

		if ( empty( $action ) || empty( $action['request'] ) ) {
			throw new \Exception( 'Unknown action "' . esc_html( $event ) . '" for custom app "' . esc_html( static::get_slug() ) . '".' );
		}

		$context = Template::build_context( $config, $credentials );
		$request = RequestBuilder::build( $action['request'], $manifest, $context );

		$response = HttpClient::request( $request['method'], $request['url'], $request['headers'], $request['body'] );

		if ( ! empty( $response['error'] ) ) {
			return [
				'port' => 'main',
				'data' => [
					'success' => false,
					'status'  => $response['status'],
					'error'   => $response['error'],
				],
			];
		}

		$outputs = ResponseMapper::map_outputs( $response['body'], is_array( $action['output'] ?? null ) ? $action['output'] : [] );

		return [
			'port' => 'main',
			'data' => array_merge(
				$outputs,
				[
					'success' => $response['status'] >= 200 && $response['status'] < 300,
					'status'  => $response['status'],
				]
			),
		];
	}

	public static function requires_connection(): bool {
		$type = (string) ( self::manifest()['auth']['type'] ?? 'none' );
		return 'none' !== $type;
	}

	public static function get_auth_type(): string {
		return (string) ( self::manifest()['auth']['type'] ?? 'none' );
	}

	public static function get_available_auth_types(): array {
		$type = self::get_auth_type();
		return 'none' === $type ? [] : [ $type ];
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		$fields = self::manifest()['auth']['fields'] ?? [];
		return is_array( $fields ) ? $fields : [];
	}

	public static function test_connection( array $credentials ): array {
		$manifest = self::manifest();
		$test     = $manifest['auth']['test'] ?? null;

		if ( ! is_array( $test ) ) {
			return [
				'success' => true,
				'message' => 'No connection test is defined for this app.',
				'details' => [],
			];
		}

		$context  = Template::build_context( [], $credentials );
		$request  = RequestBuilder::build( $test, $manifest, $context );
		$response = HttpClient::request( $request['method'], $request['url'], $request['headers'], $request['body'] );

		$ok = empty( $response['error'] ) && $response['status'] >= 200 && $response['status'] < 300;

		return [
			'success' => $ok,
			'message' => $ok ? 'Connection successful.' : ( $response['error'] ?? ( 'Request failed with status ' . $response['status'] ) ),
			'details' => [ 'status' => $response['status'] ],
		];
	}

	protected static function oauth_config(): array {
		$oauth = self::manifest()['auth']['oauth2'] ?? [];
		return is_array( $oauth ) ? $oauth : [];
	}

	public static function get_oauth_scopes(): array {
		$scopes = self::oauth_config()['scopes'] ?? [];
		return is_array( $scopes ) ? $scopes : [];
	}

	public static function get_oauth_auth_url( string $redirect_uri, string $state, array $credentials = [] ): ?string {
		$oauth     = self::oauth_config();
		$authorize = (string) ( $oauth['authorize_url'] ?? '' );
		$client_id = (string) ( $credentials['client_id'] ?? '' );

		if ( '' === $authorize || '' === $client_id ) {
			return null;
		}

		return \Zaplane\Framework\Classes\OAuthHandler::build_auth_url(
			$authorize,
			$client_id,
			$redirect_uri,
			$state,
			self::get_oauth_scopes(),
			(array) ( $oauth['auth_params'] ?? [] )
		);
	}

	public static function exchange_oauth_code( string $code, string $redirect_uri, array $credentials = [] ): array {
		$oauth     = self::oauth_config();
		$token_url = (string) ( $oauth['token_url'] ?? '' );
		if ( '' === $token_url ) {
			return [];
		}

		$params = array_merge(
			[
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'redirect_uri'  => $redirect_uri,
				'client_id'     => (string) ( $credentials['client_id'] ?? '' ),
				'client_secret' => (string) ( $credentials['client_secret'] ?? '' ),
			],
			(array) ( $oauth['token_params'] ?? [] )
		);

		return self::token_request( $token_url, $params, $oauth );
	}

	public static function refresh_oauth_token( array $credentials ): array {
		$oauth = self::oauth_config();
		$url   = (string) ( $oauth['refresh_url'] ?? $oauth['token_url'] ?? '' );

		if ( '' === $url || empty( $credentials['refresh_token'] ) ) {
			return [];
		}

		$params = [
			'grant_type'    => 'refresh_token',
			'refresh_token' => (string) $credentials['refresh_token'],
			'client_id'     => (string) ( $credentials['client_id'] ?? '' ),
			'client_secret' => (string) ( $credentials['client_secret'] ?? '' ),
		];

		return self::token_request( $url, $params, $oauth );
	}

	protected static function token_request( string $url, array $params, array $oauth ): array {
		$headers = [
			'Content-Type' => 'application/x-www-form-urlencoded',
			'Accept'       => 'application/json',
		];

		$response = HttpClient::request( 'POST', $url, $headers, http_build_query( $params ) );

		if ( ! empty( $response['error'] ) || ! is_array( $response['body'] ) ) {
			throw new \Exception( 'OAuth token request failed: ' . esc_html( $response['error'] ?? ( 'HTTP ' . $response['status'] ) ) );
		}

		return self::map_tokens( $response['body'], $oauth );
	}

	protected static function map_tokens( array $body, array $oauth ): array {
		$map    = is_array( $oauth['token_map'] ?? null ) ? $oauth['token_map'] : [];
		$tokens = $body;

		foreach ( [ 'access_token', 'refresh_token', 'expires_in' ] as $standard ) {
			$source = (string) ( $map[ $standard ] ?? $standard );
			if ( array_key_exists( $source, $body ) ) {
				$tokens[ $standard ] = $body[ $source ];
			}
		}

		return $tokens;
	}

	protected static function index_events( $events ): array {
		if ( ! is_array( $events ) ) {
			return [];
		}

		$indexed = [];
		foreach ( $events as $event ) {
			if ( ! is_array( $event ) || empty( $event['key'] ) ) {
				continue;
			}
			$key             = (string) $event['key'];
			$label           = (string) ( $event['label'] ?? '' );
			$indexed[ $key ] = [
				// Blank labels would surface as empty dropdown options; use the key.
				'label' => '' !== $label ? $label : $key,
			];
		}
		return $indexed;
	}

	protected static function find_event( $events, string $key ): array {
		if ( ! is_array( $events ) || '' === $key ) {
			return [];
		}
		foreach ( $events as $event ) {
			if ( is_array( $event ) && (string) ( $event['key'] ?? '' ) === $key ) {
				return $event;
			}
		}
		return [];
	}
}
