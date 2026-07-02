<?php
namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Core\IntegrationLoader;
use Zaplane\Framework\Exceptions\ConnectionException;
use Zaplane\Framework\Exceptions\IntegrationException;
use Zaplane\Framework\Exceptions\OAuthException;

class OAuthHandler {


	private ConnectionManager $connections;
	private const STATE_TRANSIENT_PREFIX = 'zaplane_oauth_state_';
	private const STATE_TTL = 600;

	public function __construct( ConnectionManager $connections ) {
		$this->connections = $connections;
	}



	public function init_flow( string $app, int $user_id, string $connection_name, array $credentials, ?string $icon = null ): array {

		if ( ! IntegrationLoader::has( $app ) ) {
			throw IntegrationException::notFound( esc_html( $app ) );
		}

		$integration = IntegrationLoader::get( $app );
		$class = get_class( $integration );

		if ( $class::get_auth_type() !== 'oauth2' && $class::get_auth_type() !== 'both' ) {
			throw OAuthException::notSupported( esc_html( $app ) );
		}

		$redirect_uri = self::get_callback_url();

		$state = $this->generate_state(
			[
				'app'         => $app,
				'user_id'     => $user_id,
				'name'        => $connection_name,
				'icon'        => $icon,
				'credentials' => $credentials,
			]
		);

		$auth_url = $class::get_oauth_auth_url( $redirect_uri, $state, $credentials );

		if ( ! $auth_url ) {
			throw OAuthException::authUrlFailed( esc_html( $app ) );
		}

		return [
			'auth_url' => $auth_url,
			'state'    => $state,
		];
	}



	public function handle_callback( string $state, string $code ): int {
		$state_data = $this->validate_state( $state );

		if ( ! $state_data ) {
			throw OAuthException::invalidState();
		}

		$app = $state_data['app'];
		$user_id = $state_data['user_id'];
		$name = $state_data['name'];
		$icon = $state_data['icon'] ?? null;
		$credentials = $state_data['credentials'] ?? [];

		if ( ! IntegrationLoader::has( $app ) ) {
			throw IntegrationException::notFound( esc_html( $app ) );
		}

		$integration = IntegrationLoader::get( $app );
		$class = get_class( $integration );

		$redirect_uri = self::get_callback_url();

		try {
			$tokens = $class::exchange_oauth_code( $code, $redirect_uri, $credentials );
		} catch ( \Throwable $e ) {
			throw OAuthException::tokenExchangeFailed( esc_html( $app ), esc_html( $e->getMessage() ) );
		}

		if ( empty( $tokens['access_token'] ) ) {
			throw OAuthException::noAccessToken( esc_html( $app ) );
		}

			$merged_credentials = array_merge( $credentials, $tokens );

			// Fall back to the integration's own icon if the caller didn't provide one,
			// so OAuth connections render with the correct app icon instead of the placeholder.
			if ( empty( $icon ) && method_exists( $class, 'get_icon' ) ) {
				$icon = $class::get_icon();
			}

			$create_result = $this->connections->create(
				$user_id,
				$app,
				$name,
				'oauth2',
				$merged_credentials,
				$icon
			);
			$connection_id = $create_result['id'];

			if ( isset( $tokens['expires_in'] ) ) {
				$this->connections->set_oauth_expiry( $connection_id, (int) $tokens['expires_in'] );
			}

		delete_transient( self::STATE_TRANSIENT_PREFIX . $state );

		return $connection_id;
	}

	public static function get_callback_url(): string {
		return rest_url( 'zaplane/v1/connections/oauth/callback' );
	}

	private function generate_state( array $data ): string {
		$state = bin2hex( random_bytes( 32 ) );

		set_transient(
			self::STATE_TRANSIENT_PREFIX . $state,
			$data,
			self::STATE_TTL
		);

		return $state;
	}

	private function validate_state( string $state ): ?array {
		$data = get_transient( self::STATE_TRANSIENT_PREFIX . $state );

		if ( false === $data ) {
			return null;
		}

		return $data;
	}

	public static function build_auth_url(
		string $base_url,
		string $client_id,
		string $redirect,
		string $state,
		array $scopes = [],
		array $extra = []
	): string {
		$params = array_merge(
			[
				'client_id'     => $client_id,
				'redirect_uri'  => $redirect,
				'state'         => $state,
				'response_type' => 'code',
			],
			$extra
		);

		if ( ! empty( $scopes ) ) {
			$params['scope'] = implode( ' ', $scopes );
		}

		return $base_url . '?' . http_build_query( $params );
	}
}
