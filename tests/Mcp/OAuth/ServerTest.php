<?php

namespace Zaplane\Tests\Mcp\OAuth;

use Zaplane\Mcp\OAuth\ClientStore;
use Zaplane\Mcp\OAuth\Discovery;
use Zaplane\Mcp\OAuth\Server;
use Zaplane\Mcp\TokenStore;
use Zaplane\Settings;
use Zaplane\Tests\TestCase;

class ServerTest extends TestCase {

	private const VERIFIER = 'a-verifier-long-enough-to-be-legal-0123456789';

	protected function setUp(): void {
		parent::setUp();

		$settings                          = Settings::get();
		$settings['features']['mcp_server'] = true;
		update_option( 'zaplane_settings', $settings );
	}

	private function challenge( string $verifier ): string {
		return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
	}

	/** Stand in for the redirect the consent screen would have issued. */
	private function issue_code( array $overrides = [] ): string {
		$code = 'zac_' . wp_generate_password( 40, false );

		set_transient(
			'zaplane_mcp_authcode_' . hash( 'sha256', $code ),
			array_merge(
				[
					'client_id'    => 'zpc_test',
					'client_name'  => 'Claude',
					'redirect_uri' => 'https://claude.ai/cb',
					'challenge'    => $this->challenge( self::VERIFIER ),
					'scopes'       => [ 'read', 'write' ],
					'user_id'      => 7,
				],
				$overrides
			),
			MINUTE_IN_SECONDS
		);

		return $code;
	}

	private function request( array $params ): \WP_REST_Request {
		$request = new \WP_REST_Request( 'POST', '/zaplane/v1/oauth/token' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $request;
	}

	private function exchange( array $overrides = [] ): array {
		$code = $this->issue_code();

		return (array) Server::token(
			$this->request(
				array_merge(
					[
						'grant_type'    => 'authorization_code',
						'code'          => $code,
						'client_id'     => 'zpc_test',
						'redirect_uri'  => 'https://claude.ai/cb',
						'code_verifier' => self::VERIFIER,
					],
					$overrides
				)
			)
		);
	}

	/**
	 * @test
	 */
	public function it_exchanges_a_code_for_an_access_token_and_a_refresh_token(): void {
		$result = $this->exchange();

		$this->assertStringStartsWith( 'zpl_', $result['access_token'] );
		$this->assertStringStartsWith( 'zpr_', $result['refresh_token'] );
		$this->assertSame( 'Bearer', $result['token_type'] );
		$this->assertSame( Server::TOKEN_TTL, $result['expires_in'] );
		$this->assertSame( 'read write', $result['scope'] );

		// The token works, and belongs to the administrator who approved it.
		$record = TokenStore::resolve( $result['access_token'] );
		$this->assertNotNull( $record );
		$this->assertSame( 7, (int) $record['user_id'] );
		$this->assertSame( 'zpc_test', (string) $record['client_id'] );
	}

	/**
	 * @test
	 */
	public function it_refuses_a_verifier_that_does_not_match_the_challenge(): void {
		$result = Server::token(
			$this->request(
				[
					'grant_type'    => 'authorization_code',
					'code'          => $this->issue_code(),
					'client_id'     => 'zpc_test',
					'redirect_uri'  => 'https://claude.ai/cb',
					'code_verifier' => 'not-the-verifier',
				]
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_grant', $result->get_error_code() );
	}

	/**
	 * @test
	 */
	public function it_refuses_a_missing_verifier(): void {
		$result = $this->exchangeRaw( [ 'code_verifier' => '' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_grant', $result->get_error_code() );
	}

	/**
	 * @return array<string,mixed>|\WP_Error
	 */
	private function exchangeRaw( array $overrides ) {
		return Server::token(
			$this->request(
				array_merge(
					[
						'grant_type'    => 'authorization_code',
						'code'          => $this->issue_code(),
						'client_id'     => 'zpc_test',
						'redirect_uri'  => 'https://claude.ai/cb',
						'code_verifier' => self::VERIFIER,
					],
					$overrides
				)
			)
		);
	}

	/**
	 * @test
	 */
	public function it_refuses_a_code_presented_by_a_different_client(): void {
		$result = $this->exchangeRaw( [ 'client_id' => 'zpc_someone_else' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_grant', $result->get_error_code() );
	}

	/**
	 * @test
	 */
	public function it_refuses_a_redirect_uri_that_is_not_the_one_the_code_was_issued_for(): void {
		$result = $this->exchangeRaw( [ 'redirect_uri' => 'https://claude.ai/other' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_grant', $result->get_error_code() );
	}

	/**
	 * @test
	 */
	public function it_spends_a_code_exactly_once(): void {
		$code    = $this->issue_code();
		$params  = [
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'client_id'     => 'zpc_test',
			'redirect_uri'  => 'https://claude.ai/cb',
			'code_verifier' => self::VERIFIER,
		];

		$first = Server::token( $this->request( $params ) );
		$this->assertIsArray( $first );

		$replay = Server::token( $this->request( $params ) );
		$this->assertInstanceOf( \WP_Error::class, $replay );
		$this->assertSame( 'invalid_grant', $replay->get_error_code() );
	}

	/**
	 * A wrong verifier must not leave the code spendable for a second attempt.
	 *
	 * @test
	 */
	public function it_burns_the_code_even_when_the_verifier_was_wrong(): void {
		$code   = $this->issue_code();
		$params = [
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'client_id'     => 'zpc_test',
			'redirect_uri'  => 'https://claude.ai/cb',
			'code_verifier' => self::VERIFIER,
		];

		$failed = Server::token( $this->request( array_merge( $params, [ 'code_verifier' => 'wrong' ] ) ) );
		$this->assertInstanceOf( \WP_Error::class, $failed );

		$retry = Server::token( $this->request( $params ) );
		$this->assertInstanceOf( \WP_Error::class, $retry );
	}

	/**
	 * @test
	 */
	public function it_rotates_a_refresh_token_and_retires_the_one_it_replaces(): void {
		$first = $this->exchange();

		$second = (array) Server::token(
			$this->request(
				[
					'grant_type'    => 'refresh_token',
					'refresh_token' => $first['refresh_token'],
					'client_id'     => 'zpc_test',
				]
			)
		);

		$this->assertStringStartsWith( 'zpl_', $second['access_token'] );
		$this->assertNotSame( $first['access_token'], $second['access_token'] );
		$this->assertSame( 'read write', $second['scope'] );

		// The replaced pair is gone: neither the old access token nor the spent
		// refresh token works any more.
		$this->assertNull( TokenStore::resolve( $first['access_token'] ) );
		$this->assertNotNull( TokenStore::resolve( $second['access_token'] ) );

		$replay = Server::token(
			$this->request(
				[
					'grant_type'    => 'refresh_token',
					'refresh_token' => $first['refresh_token'],
					'client_id'     => 'zpc_test',
				]
			)
		);
		$this->assertInstanceOf( \WP_Error::class, $replay );
	}

	/**
	 * @test
	 */
	public function it_refuses_an_unknown_grant_type(): void {
		$result = Server::token( $this->request( [ 'grant_type' => 'password' ] ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'unsupported_grant_type', $result->get_error_code() );
	}

	/**
	 * @test
	 */
	public function it_revokes_a_token_without_saying_whether_it_existed(): void {
		$issued = $this->exchange();

		$this->assertSame( [ 'revoked' => true ], Server::revoke( $this->request( [ 'token' => $issued['access_token'] ] ) ) );
		$this->assertNull( TokenStore::resolve( $issued['access_token'] ) );

		// An unknown token is not an error — answering differently would let a
		// caller probe which tokens exist.
		$this->assertSame( [ 'revoked' => true ], Server::revoke( $this->request( [ 'token' => 'zpl_nope.nope' ] ) ) );
	}

	/**
	 * @test
	 */
	public function it_registers_a_client_through_the_rest_shape(): void {
		$request = new \WP_REST_Request( 'POST', '/zaplane/v1/oauth/register' );
		$request->set_json_params(
			[
				'client_name'   => 'ChatGPT',
				'redirect_uris' => [ 'https://chatgpt.com/connector_platform_oauth_redirect' ],
			]
		);

		$client = (array) Server::register( $request );

		$this->assertStringStartsWith( 'zpc_', $client['client_id'] );
		$this->assertSame( 'none', $client['token_endpoint_auth_method'] );
		$this->assertSame( 0, $client['client_secret_expires_at'] );
		$this->assertNotNull( ClientStore::get( $client['client_id'] ) );
	}

	/**
	 * @test
	 */
	public function it_answers_nothing_when_the_mcp_module_is_off(): void {
		$settings                           = Settings::get();
		$settings['features']['mcp_server'] = false;
		update_option( 'zaplane_settings', $settings );

		$register = Server::register( new \WP_REST_Request( 'POST', '/x' ) );
		$this->assertInstanceOf( \WP_Error::class, $register );
		$this->assertSame( 404, $register->get_error_data()['status'] );

		$token = Server::token( $this->request( [ 'grant_type' => 'authorization_code' ] ) );
		$this->assertInstanceOf( \WP_Error::class, $token );
		$this->assertSame( 404, $token->get_error_data()['status'] );
	}

	/**
	 * @test
	 */
	public function the_discovery_documents_describe_endpoints_that_exist(): void {
		$resource = Discovery::protected_resource_document();

		$this->assertSame( Discovery::resource_url(), $resource['resource'] );
		$this->assertSame( [ Discovery::issuer() ], $resource['authorization_servers'] );
		$this->assertSame( TokenStore::ALL_SCOPES, $resource['scopes_supported'] );

		$as = Discovery::authorization_server_document();

		// S256 only: "plain" is in the spec and protects nothing.
		$this->assertSame( [ 'S256' ], $as['code_challenge_methods_supported'] );
		$this->assertSame( [ 'none' ], $as['token_endpoint_auth_methods_supported'] );
		$this->assertSame( [ 'authorization_code', 'refresh_token' ], $as['grant_types_supported'] );
		$this->assertSame( Server::authorize_url(), $as['authorization_endpoint'] );

		// The consent screen is a front-end URL, not a REST route, because the
		// REST stack drops the cookie-signed-in user without a wp_rest nonce.
		$this->assertStringNotContainsString( 'wp-json', $as['authorization_endpoint'] );
		$this->assertStringNotContainsString( 'rest_route', $as['authorization_endpoint'] );

		foreach ( [ 'token_endpoint', 'registration_endpoint', 'revocation_endpoint' ] as $key ) {
			$this->assertNotEmpty( $as[ $key ], $key . ' must be advertised' );
		}
	}
}
