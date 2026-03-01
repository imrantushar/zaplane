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

    /**
     * @throws OAuthException
     * @throws IntegrationException
     */
    public function init_flow(string $app, int $user_id, string $connection_name, array $credentials ): array {
		// Check if integration exists
		if ( ! IntegrationLoader::has( $app ) ) {
			throw IntegrationException::notFound( $app );
		}

		// Get integration class
		$integration = IntegrationLoader::get( $app );
		$class = get_class( $integration );

		// Check if integration supports OAuth2
		if ( $class::get_auth_type() !== 'oauth2' && $class::get_auth_type() !== 'both' ) {
			throw OAuthException::notSupported( $app );
		}

		$redirect_uri = self::get_callback_url();

		$state = $this->generate_state(
			array(
				'app'         => $app,
				'user_id'     => $user_id,
				'name'        => $connection_name,
				'credentials' => $credentials,
			)
		);

		// Get OAuth authorization URL
		$auth_url = $class::get_oauth_auth_url( $redirect_uri, $state, $credentials );

		if ( ! $auth_url ) {
			throw OAuthException::authUrlFailed( $app );
		}

		return array(
			'auth_url' => $auth_url,
			'state'    => $state,
		);
	}

    /**
     * @throws OAuthException
     * @throws IntegrationException
     * @throws ConnectionException
     */
    public function handle_callback(string $state, string $code ): int {
		$state_data = $this->validate_state( $state );

		if ( ! $state_data ) {
			throw OAuthException::invalidState();
		}

		$app = $state_data['app'];
		$user_id = $state_data['user_id'];
		$name = $state_data['name'];
		$credentials = $state_data['credentials'] ?? array();

		// Get integration class
		if ( ! IntegrationLoader::has( $app ) ) {
			throw IntegrationException::notFound( $app );
		}

		$integration = IntegrationLoader::get( $app );
		$class = get_class( $integration );

		$redirect_uri = self::get_callback_url();

		try {
			$tokens = $class::exchange_oauth_code( $code, $redirect_uri, $credentials );
		} catch ( \Throwable $e ) {
			throw OAuthException::tokenExchangeFailed( $app, $e->getMessage() );
		}

		if ( empty( $tokens['access_token'] ) ) {
			throw OAuthException::noAccessToken( $app );
		}

        $create_result = $this->connections->create(
			$user_id,
			$app,
			$name,
			'oauth2',
            $credentials
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
