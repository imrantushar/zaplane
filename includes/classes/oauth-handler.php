<?php
namespace Zaplane\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Core\IntegrationLoader;
use Zaplane\Exceptions\IntegrationException;
use Zaplane\Exceptions\OAuthException;

class OAuthHandler {

	private ConnectionManager $connections;
	private const STATE_TRANSIENT_PREFIX = 'zaplane_oauth_state_';
	private const STATE_TTL = 600;

	public function __construct( ConnectionManager $connections ) {
		$this->connections = $connections;
	}

	public function init_flow( string $app, int $user_id, string $connection_name ): array {
		$integration = IntegrationLoader::get( $app );

		if ( ! $integration ) {
			throw IntegrationException::notFound( $app );
		}

		if ( $integration::get_auth_type() !== 'oauth2' ) {
			throw OAuthException::notSupported( $app );
		}

		$redirect_uri = self::get_callback_url();

		$state = $this->generate_state(
			array(
				'app'         => $app,
				'user_id'     => $user_id,
				'name'        => $connection_name,
				'credentials' => $credentials, // Store for token exchange
			)
		);

		$auth_url = $integration::get_oauth_auth_url( $redirect_uri, $state );

		if ( ! $auth_url ) {
			throw OAuthException::authUrlFailed( $app );
		}

		return array(
			'auth_url' => $auth_url,
			'state'    => $state,
		);
	}

	public function handle_callback( string $state, string $code ): int {
		$state_data = $this->validate_state( $state );

		if ( ! $state_data ) {
			throw OAuthException::invalidState();
		}

		$app = $state_data['app'];
		$user_id = $state_data['user_id'];
		$name = $state_data['name'];
		$credentials = $state_data['credentials'] ?? array();

		// Ensure registry is loaded
		IntegrationLoader::init();
		$integration = IntegrationLoader::get( $app );

		if ( ! $integration ) {
			throw IntegrationException::notFound( $app );
		}

		$redirect_uri = self::get_callback_url();

		try {
			$tokens = $integration::exchange_oauth_code( $code, $redirect_uri );
		} catch ( \Throwable $e ) {
			throw OAuthException::tokenExchangeFailed( $app, $e->getMessage() );
		}

		if ( empty( $tokens['access_token'] ) ) {
			throw OAuthException::noAccessToken( $app );
		}

		$connection_id = $this->connections->create(
			$user_id,
			$app,
			$name,
			'oauth2',
			$final_credentials
		);

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

		if ( $data === false ) {
			return null;
		}

		return $data;
	}

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
