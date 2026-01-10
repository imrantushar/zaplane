<?php
namespace Zaplane\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Core\IntegrationLoader;

/**
 * OAuth 2.0 Flow Handler
 * Manages OAuth authorization flows for integrations
 */
class OAuthHandler {

	private ConnectionManager $connections;
	private const STATE_TRANSIENT_PREFIX = 'zaplane_oauth_state_';
	private const STATE_TTL = 600; // 10 minutes

	public function __construct( ConnectionManager $connections ) {
		$this->connections = $connections;
	}

	/**
	 * Initialize OAuth flow
	 *
	 * @param string $app             Integration slug
	 * @param int    $user_id         WordPress user ID
	 * @param string $connection_name User-defined connection name
	 * @return array ['auth_url' => string, 'state' => string]
	 * @throws \Exception on failure
	 */
	public function init_flow( string $app, int $user_id, string $connection_name ): array {
		$integration = IntegrationLoader::get( $app );

		if ( ! $integration ) {
			throw new \Exception( 'Integration not found: ' . $app );
		}

		if ( $integration::get_auth_type() !== 'oauth2' ) {
			throw new \Exception( 'Integration does not support OAuth2' );
		}

		$redirect_uri = self::get_callback_url();

		// Generate and store state token
		$state = $this->generate_state(
			array(
				'app'     => $app,
				'user_id' => $user_id,
				'name'    => $connection_name,
			)
		);

		// Get authorization URL from integration
		$auth_url = $integration::get_oauth_auth_url( $redirect_uri, $state );

		if ( ! $auth_url ) {
			throw new \Exception( 'Failed to generate OAuth authorization URL' );
		}

		return array(
			'auth_url' => $auth_url,
			'state'    => $state,
		);
	}

	/**
	 * Handle OAuth callback
	 *
	 * @param string $state CSRF state token
	 * @param string $code  Authorization code
	 */
	public function handle_callback( string $state, string $code ) {
		// Validate and retrieve state data
		$state_data = $this->validate_state( $state );

		if ( ! $state_data ) {
			return new \WP_Error(
				'invalid_state',
				'Invalid or expired OAuth state token'
			);
		}

		$app = $state_data['app'];
		$user_id = $state_data['user_id'];
		$name = $state_data['name'];

		$integration = IntegrationLoader::get( $app );

		if ( ! $integration ) {
			return new \WP_Error( 'invalid_app', 'Integration not found' );
		}

		$redirect_uri = self::get_callback_url();

		// Exchange code for tokens
		try {
			$tokens = $integration::exchange_oauth_code( $code, $redirect_uri );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'token_exchange_failed', $e->getMessage() );
		}

		if ( empty( $tokens['access_token'] ) ) {
			return new \WP_Error(
				'no_access_token',
				'OAuth token exchange did not return an access token'
			);
		}

		// Create the connection with tokens as credentials
		$connection_id = $this->connections->create(
			$user_id,
			$app,
			$name,
			'oauth2',
			$tokens
		);

		if ( is_wp_error( $connection_id ) ) {
			return $connection_id;
		}

		// Set OAuth expiry if provided
		if ( isset( $tokens['expires_in'] ) ) {
			$this->connections->set_oauth_expiry( $connection_id, (int) $tokens['expires_in'] );
		}

		// Clean up state transient
		delete_transient( self::STATE_TRANSIENT_PREFIX . $state );

		return $connection_id;
	}

	/**
	 * Get the callback URL for OAuth flows
	 *
	 * @return string REST API callback endpoint
	 */
	public static function get_callback_url(): string {
		return rest_url( 'zaplane/v1/connections/oauth/callback' );
	}

	/**
	 * Generate and store state token
	 *
	 * @param array $data Data to store with state
	 * @return string State token
	 */
	private function generate_state( array $data ): string {
		$state = bin2hex( random_bytes( 32 ) );

		set_transient(
			self::STATE_TRANSIENT_PREFIX . $state,
			$data,
			self::STATE_TTL
		);

		return $state;
	}

	/**
	 * Validate and retrieve state data
	 *
	 * @param string $state State token
	 * @return array|null State data or null if invalid/expired
	 */
	private function validate_state( string $state ): ?array {
		$data = get_transient( self::STATE_TRANSIENT_PREFIX . $state );

		if ( $data === false ) {
			return null;
		}

		return $data;
	}

	/**
	 * Build authorization URL with query parameters
	 *
	 * @param string $base_url   OAuth provider's authorize endpoint
	 * @param string $client_id  Application client ID
	 * @param string $redirect   Redirect URI
	 * @param string $state      CSRF state token
	 * @param array  $scopes     Required scopes
	 * @param array  $extra      Additional query parameters
	 * @return string Full authorization URL
	 */
	public static function build_auth_url(
		string $base_url,
		string $client_id,
		string $redirect,
		string $state,
		array $scopes = array(),
		array $extra = array()
	): string {
		$params = array_merge(
			array(
				'client_id'     => $client_id,
				'redirect_uri'  => $redirect,
				'state'         => $state,
				'response_type' => 'code',
			),
			$extra
		);

		if ( ! empty( $scopes ) ) {
			$params['scope'] = implode( ' ', $scopes );
		}

		return $base_url . '?' . http_build_query( $params );
	}
}
