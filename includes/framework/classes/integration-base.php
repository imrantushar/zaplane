<?php
namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class IntegrationBase {


	abstract public static function get_slug(): string;

	public static function get_name(): string {
		return ucfirst( static::get_slug() );
	}

	/**
	 * Plugin basenames this integration needs active to function, e.g.
	 * [ 'woocommerce/woocommerce.php' ]. Empty means it only relies on WP core.
	 *
	 * By default this reads the central map in config/integration-plugins.php
	 * keyed by slug — so you declare dependencies in ONE place instead of editing
	 * every integration. Override this method only when the dependency is
	 * conditional/dynamic. Filterable via `zaplane_integration_required_plugins`.
	 *
	 * Used by the recipe testing CLI to auto-activate dependencies before a live
	 * run, and available for the admin UI to surface "requires X".
	 */
	public static function get_required_plugins(): array {
		$slug = static::get_slug();
		$map  = function_exists( 'zaplane_config' ) ? (array) zaplane_config( 'integration-plugins', [] ) : [];
		$required = isset( $map[ $slug ] ) ? (array) $map[ $slug ] : [];

		return array_values( (array) apply_filters( 'zaplane_integration_required_plugins', $required, $slug ) );
	}

	/**
	 * Trigger events this integration can create sample data for, so the recipe
	 * tester can fire them with real data and no per-recipe factory. Capability
	 * declaration only — must have NO side effects.
	 *
	 * @return string[]
	 */
	public static function get_seedable_triggers(): array {
		return [];
	}

	/**
	 * Create the real data a trigger needs and return the positional hook
	 * arguments to fire it with (matching what WordPress/the plugin really fires).
	 * Called by the recipe tester when a recipe has no input/factory of its own.
	 *
	 * Return null when the event isn't seedable. Only override for events listed
	 * in get_seedable_triggers().
	 *
	 * @return array|null Positional hook args, e.g. [ $order_id, $order ].
	 */
	public static function seed_trigger_args( string $event ): ?array {
		return null;
	}

	/**
	 * Action events the recipe tester can run with sample config and assert a
	 * successful result. Capability declaration only — no side effects.
	 *
	 * @return string[]
	 */
	public static function get_testable_actions(): array {
		return [];
	}

	/**
	 * A valid config to execute an action with for testing (creating any
	 * prerequisite data first, e.g. an order id for update_order). Called by the
	 * recipe tester when an action recipe has no config of its own. Null when the
	 * action isn't testable. Only override for events in get_testable_actions().
	 */
	public static function get_sample_action_config( string $event ): ?array {
		return null;
	}

	public static function get_icon(): string {
		return '';
	}

	public static function get_category(): string {
		return 'app';
	}

	/**
	 * Public zaplane.app/docs/ links for this integration, so the trigger/action
	 * picker can offer a "View docs" link next to it instead of leaving the user
	 * to search the knowledge base by hand.
	 *
	 * Most integrations have one page covering both triggers and actions — return
	 * the same URL for both keys. A few have the trigger and action documented on
	 * separate pages; only then set the two keys differently. Empty string means
	 * no doc page exists yet for that half.
	 *
	 * @return array{trigger:string,action:string}
	 */
	public static function get_docs_url(): array {
		return [
			'trigger' => '',
			'action'  => '',
		];
	}





	public static function get_triggers(): array {
		return [];
	}



	public static function get_actions(): array {
		return [];
	}





	public static function resolve_trigger( array $node, array $hook_args ) {
		return false;
	}



	public static function execute_node( array $node, array $input ): array {
		return [
			'port' => 'main',
			'data' => $input,
		];
	}



	public static function validate_config( array $config ): bool {
		return true;
	}

	public static function get_config_schema(): array {
		return [];
	}



	public static function get_output_ports(): array {
		return [ 'main' ];
	}



	public static function supports_webhook(): bool {
		return false;
	}



	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		return true;
	}



	public static function parse_webhook_event( \WP_REST_Request $request ): ?array {
		return null;
	}

	/**
	 * Handle the provider's webhook-subscription verification handshake.
	 *
	 * Meta (WhatsApp Cloud API / Messenger) pings the callback URL with a GET
	 * carrying hub.mode / hub.verify_token / hub.challenge — PHP turns the dots
	 * into underscores, so they arrive as hub_mode / hub_verify_token /
	 * hub_challenge. Return the challenge string to echo back on success, or
	 * null to reject. Override per integration to source the token elsewhere
	 * (e.g. a stored connection credential).
	 */
	public static function verify_webhook_challenge( \WP_REST_Request $request ): ?string {
		$mode      = $request->get_param( 'hub_mode' );
		$token     = $request->get_param( 'hub_verify_token' );
		$challenge = $request->get_param( 'hub_challenge' );

		if ( 'subscribe' !== $mode || null === $challenge ) {
			return null;
		}

		$expected = static::get_webhook_verify_token();

		if ( '' !== $expected && hash_equals( $expected, (string) $token ) ) {
			return (string) $challenge;
		}

		return null;
	}

	/**
	 * Answer a handshake the provider performs over the same POST endpoint it
	 * later delivers events to (Slack's `url_verification`, for example). Return
	 * the raw body to echo back, or null when this request is a normal event.
	 *
	 * The GET-style handshake (Meta's hub.challenge) is handled separately by
	 * verify_webhook_challenge().
	 *
	 * @return array{body:string,content_type:string}|null
	 */
	public static function handle_webhook_handshake( \WP_REST_Request $request ): ?array {
		return null;
	}

	/**
	 * Fields the site owner must fill in before this integration's incoming
	 * webhook can be used — verify tokens, signing secrets, shared secrets.
	 *
	 * Rendered by the trigger drawer's Webhook Setup panel and persisted through
	 * the /incoming/<slug>/config REST route. Each entry:
	 *   key, label, type ('text'|'password'), help, generate (bool)
	 *
	 * `generate` marks a value the user invents rather than copies from the
	 * provider, so the UI can offer to generate one.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_webhook_setup_fields(): array {
		return [];
	}

	/**
	 * Called by IncomingWebhookController::update_webhook_config() right after
	 * the Webhook Setup panel's fields are persisted to zaplane_webhook_config.
	 *
	 * $config is the saved bucket for this integration (the same keys declared
	 * in get_webhook_setup_fields(), e.g. ['secret_token' => '...']). Override
	 * to push the new settings to the provider's own API — e.g. calling
	 * Telegram's setWebhook — so Save actually (re)registers the webhook with
	 * the provider instead of only persisting it locally. No-op by default:
	 * most integrations (Slack, Meta) verify via a handshake instead and don't
	 * need an outbound registration call at all.
	 */
	public static function on_webhook_config_saved( array $config ): void {}

	/**
	 * Read one saved webhook setting for this integration.
	 *
	 * Values live together under the `zaplane_webhook_config` option keyed by
	 * slug. $legacy_option names the standalone option an earlier version wrote
	 * to, so sites that set one by hand keep working.
	 */
	public static function get_webhook_setting( string $key, string $legacy_option = '' ): string {
		$slug   = static::get_slug();
		$config = get_option( 'zaplane_webhook_config', [] );
		$value  = '';

		if ( is_array( $config ) && isset( $config[ $slug ][ $key ] ) && is_scalar( $config[ $slug ][ $key ] ) ) {
			$value = trim( (string) $config[ $slug ][ $key ] );
		}

		if ( '' === $value && '' !== $legacy_option ) {
			$value = trim( (string) get_option( $legacy_option, '' ) );
		}

		return (string) apply_filters( 'zaplane_webhook_setting', $value, $key, $slug );
	}

	/**
	 * The verify token this integration expects during the webhook handshake.
	 * Stored per integration as an option and filterable; integrations may
	 * override to pull it from a connection credential instead.
	 */
	public static function get_webhook_verify_token(): string {
		$slug  = static::get_slug();
		$token = static::get_webhook_setting( 'verify_token', 'zaplane_webhook_verify_token_' . $slug );

		return (string) apply_filters( 'zaplane_webhook_verify_token', $token, $slug );
	}

	public static function supports_polling(): bool {
		return false;
	}

	public static function get_rate_limit(): int {
		return 0;
	}



	public static function get_trigger_config_schema( string $trigger ): array {
		return [];
	}



	/**
	 * Return a sample output array for a trigger event.
	 *
	 * Keys must mirror what resolve_trigger() returns for the same event.
	 * The condition-variables API uses this when no real test run exists yet,
	 * so users can still pick trigger fields in the condition builder.
	 *
	 * To add sample output for a new integration, override this method and
	 * return a keyed array: [ 'trigger_slug' => [ 'field' => 'sample', ... ], ... ]
	 */
	public static function get_trigger_sample_output( string $trigger ): array {
		return [];
	}



	public static function get_action_config_schema( string $action ): array {
		return [];
	}



	/**
	 * A sample of the data an action returns, so downstream nodes can offer its
	 * output fields in the "@" variable picker before any test run has happened.
	 *
	 * Mirrors get_trigger_sample_output() for actions. Keys must match what
	 * execute_node() actually returns under its `data`. Return [] to opt out.
	 */
	public static function get_action_sample_output( string $action ): array {
		return [];
	}



	public static function get_dynamic_fields(): array {
		return [];
	}





	public static function requires_connection(): bool {
		return false;
	}



	public static function get_auth_type(): string {
		return 'none';
	}



	public static function get_available_auth_types(): array {
		return [];
	}



	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [];
	}



	public static function test_connection( array $credentials ): array {
		return [
			'success' => true,
			'message' => 'Connection test not implemented for this integration',
			'details' => [],
		];
	}





	public static function get_oauth_auth_url( string $redirect_uri, string $state, array $credentials = [] ): ?string {
		return null;
	}



	public static function exchange_oauth_code( string $code, string $redirect_uri, array $credentials = [] ): array {
		return [];
	}



	public static function refresh_oauth_token( array $credentials ): array {
		return [];
	}



	public static function get_oauth_scopes(): array {
		return [];
	}





	protected static function http_get( string $url, array $headers = [] ): array {
		return static::http_request( 'GET', $url, [ 'headers' => $headers ] );
	}



	protected static function http_post( string $url, array $body = [], array $headers = [] ): array {
		return static::http_request('POST', $url, [
			'headers' => array_merge( [ 'Content-Type' => 'application/json' ], $headers ),
			'body'    => wp_json_encode( $body ),
		]);
	}



	protected static function http_request( string $method, string $url, array $args = [] ): array {
		$response = wp_remote_request( $url, array_merge( [ 'method' => strtoupper( $method ) ], $args ) );
		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'HTTP request failed: ' . esc_html( $response->get_error_message() ) );
		}
		$body   = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		$status = (int) wp_remote_retrieve_response_code( $response );
		return [ $body, $status ];
	}

	/**
	 * The REST route (namespace-relative) this integration receives webhooks on.
	 * Kept relative so the built manifest never bakes in a build machine's host.
	 */
	public static function get_webhook_route(): string {
		return 'zaplane/v1/incoming/' . static::get_slug();
	}

	public static function get_webhook_url(): string {
		return rest_url( static::get_webhook_route() );
	}

	/**
	 * A distinct webhook URL for one specific connection of this integration,
	 * e.g. .../incoming/telegram/42. Integrations that need to tell multiple
	 * accounts/bots apart (so an incoming delivery can be attributed to the
	 * connection it came from, and only that connection's trigger nodes fire)
	 * register each connection at its own URL via this instead of the shared
	 * get_webhook_url(). Works the same whether there's one connection or many.
	 */
	public static function get_webhook_url_for_connection( int $connection_id ): string {
		return rest_url( static::get_webhook_route() . '/' . $connection_id );
	}
}
