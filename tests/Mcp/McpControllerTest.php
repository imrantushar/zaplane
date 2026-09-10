<?php

namespace Zaplane\Tests\Mcp;

use Zaplane\API\McpController;
use Zaplane\Authoring\Catalog;
use Zaplane\Mcp\TokenStore;
use Zaplane\Tests\TestCase;

/**
 * Drives the controller the way a client does: a JSON-RPC body plus a bearer
 * token, checking what comes back out.
 */
class McpControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Catalog::flush();
		$this->enableFeature();

		// Every token now acts as its user and is held to what that user can do.
		// These tests are about dispatch, so they run as somebody who may — the
		// gate itself is tested on its own, below.
		$GLOBALS['zaplane_test_caps'] = [ 'manage_options' ];
	}

	private function enableFeature( bool $on = true ): void {
		update_option(
			'zaplane_settings',
			wp_json_encode( [ 'features' => [ 'mcp_server' => $on ] ] )
		);
	}

	/**
	 * @param array<string,mixed> $body
	 */
	private function request( array $body, string $bearer = '', array $headers = [] ): \WP_REST_Request {
		$request = new \WP_REST_Request();
		$request->set_json_params( $body );

		if ( '' !== $bearer ) {
			$request->set_header( 'authorization', 'Bearer ' . $bearer );
		}

		foreach ( $headers as $key => $value ) {
			$request->set_header( $key, $value );
		}

		return $request;
	}

	/**
	 * @param array<string,mixed> $body
	 */
	private function rpc( array $body, string $bearer, array $headers = [] ): array {
		$controller = new McpController();
		$request    = $this->request( $body, $bearer, $headers );

		$this->assertTrue(
			$controller->check_bearer( $request ),
			'Expected the token to authenticate.'
		);

		return (array) $controller->handle_rpc( $request )->get_data();
	}

	/** The text payload a tools/call result carries, decoded. */
	private function toolText( array $response ): string {
		return (string) $response['result']['content'][0]['text'];
	}

	/* ------------------------------- auth --------------------------------- */

	/**
	 * @test
	 */
	public function it_refuses_everything_while_the_feature_is_off(): void {
		$issued = TokenStore::issue( 'Client' );
		$this->enableFeature( false );

		$controller = new McpController();

		$this->assertFalse( $controller->check_bearer( $this->request( [], $issued['token'] ) ) );
	}

	/**
	 * @test
	 */
	public function it_refuses_a_missing_or_wrong_token(): void {
		TokenStore::issue( 'Client' );
		$controller = new McpController();

		$this->assertFalse( $controller->check_bearer( $this->request( [] ) ) );
		$this->assertFalse( $controller->check_bearer( $this->request( [], 'zpl_nope.nope' ) ) );
	}

	/**
	 * @return array<string,string>
	 */
	private function diagnose(): array {
		$checks = ( new McpController() )->diagnostics()->get_data()['checks'];

		return array_column( $checks, 'status', 'key' );
	}

	/**
	 * One real fault should not produce three diagnoses. With the module off the
	 * endpoint and the discovery documents are *meant* to be unavailable, so
	 * probing them would report causes that are not the cause.
	 *
	 * @test
	 */
	public function diagnostics_do_not_pile_false_causes_on_a_disabled_module(): void {
		$this->enableFeature( false );

		$status = $this->diagnose();

		$this->assertSame( 'fail', $status['module'] );
		$this->assertSame( 'skip', $status['endpoint'] );
		$this->assertSame( 'skip', $status['discovery'] );
	}

	/**
	 * @test
	 */
	public function diagnostics_pass_when_the_endpoint_refuses_correctly(): void {
		TokenStore::issue( 'Some client' );

		// The endpoint probe, then the two discovery documents.
		\Zaplane\Tests\WPMocks::setHttpResponse( [], 401, [ 'www-authenticate' => 'Bearer realm="Zaplane MCP", resource_metadata="https://example.com/.well-known/oauth-protected-resource"' ] );
		\Zaplane\Tests\WPMocks::setHttpResponse( [ 'resource' => 'x' ], 200 );
		\Zaplane\Tests\WPMocks::setHttpResponse( [ 'issuer' => 'x' ], 200 );

		$status = $this->diagnose();

		$this->assertSame( 'ok', $status['module'] );
		$this->assertSame( 'ok', $status['endpoint'] );
		$this->assertSame( 'ok', $status['discovery'] );
		$this->assertSame( 'ok', $status['credentials'] );
	}

	/**
	 * Anything but a 401 means something in front of WordPress answered.
	 *
	 * @test
	 */
	public function diagnostics_flag_an_endpoint_that_does_not_refuse(): void {
		\Zaplane\Tests\WPMocks::setHttpResponse( [ 'ok' => true ], 200 );

		$checks = ( new McpController() )->diagnostics()->get_data()['checks'];
		$endpoint = current( array_filter( $checks, fn( $c ) => 'endpoint' === $c['key'] ) );

		$this->assertSame( 'fail', $endpoint['status'] );
		$this->assertStringContainsString( '200', $endpoint['label'] );
	}

	/**
	 * A refusal with no pointer is what a hosted connector reads as "this server
	 * does not implement OAuth".
	 *
	 * @test
	 */
	public function diagnostics_flag_a_challenge_with_no_discovery_pointer(): void {
		\Zaplane\Tests\WPMocks::setHttpResponse( [], 401, [ 'www-authenticate' => 'Bearer realm="Zaplane MCP"' ] );

		$checks = ( new McpController() )->diagnostics()->get_data()['checks'];
		$endpoint = current( array_filter( $checks, fn( $c ) => 'endpoint' === $c['key'] ) );

		$this->assertSame( 'fail', $endpoint['status'] );
		$this->assertStringContainsString( 'pointer', $endpoint['label'] );
	}

	/**
	 * The token acts as the account it was issued to, and is refused when that
	 * account cannot manage this site.
	 *
	 * This is the whole point of recording a user on a token. Before, nothing
	 * applied it: no user was set, no capability was asked, and a token issued
	 * to anybody at all reached as far as an administrator's.
	 *
	 * @test
	 */
	public function a_token_is_refused_when_its_user_cannot_manage_the_site(): void {
		$issued = TokenStore::issue( 'Client', TokenStore::DEFAULT_SCOPES, 42 );

		$GLOBALS['zaplane_test_caps'] = [];

		$controller = new McpController();
		$result     = $controller->check_bearer( $this->request( [], $issued['token'] ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Demotion has to take effect. The capability is asked on every call rather
	 * than once when the token was made, so a token outliving the standing of
	 * the person it was issued to stops working.
	 *
	 * @test
	 */
	public function the_capability_is_asked_on_every_call_not_once_at_issue(): void {
		$issued     = TokenStore::issue( 'Client', TokenStore::DEFAULT_SCOPES, 42 );
		$controller = new McpController();

		$GLOBALS['zaplane_test_caps'] = [ 'manage_options' ];
		$this->assertTrue( $controller->check_bearer( $this->request( [], $issued['token'] ) ) );

		// Same token, same everything, after the account loses the capability.
		$GLOBALS['zaplane_test_caps'] = [];
		$this->assertInstanceOf( \WP_Error::class, $controller->check_bearer( $this->request( [], $issued['token'] ) ) );
	}

	/**
	 * The token's user becomes the current user, which is what makes the
	 * capability question meaningful and the audit trail true.
	 *
	 * @test
	 */
	public function the_token_s_user_becomes_the_current_user(): void {
		$issued = TokenStore::issue( 'Client', TokenStore::DEFAULT_SCOPES, 4242 );

		$GLOBALS['zaplane_test_caps'] = [ 'manage_options' ];
		unset( $GLOBALS['zaplane_test_current_user'] );

		( new McpController() )->check_bearer( $this->request( [], $issued['token'] ) );

		$this->assertSame( 4242, $GLOBALS['zaplane_test_current_user'] ?? 0 );
	}

	/**
	 * A WordPress application password is a credential the site owner already has
	 * and already knows how to revoke, so it is honoured as a way in.
	 *
	 * @test
	 */
	public function it_accepts_a_wordpress_application_password(): void {
		$GLOBALS['zaplane_test_app_password_uuid'] = 'e7f1c0aa-0000-4000-8000-000000000001';
		$GLOBALS['zaplane_test_caps']              = [ 'manage_options' ];
		$controller = new McpController();

		$this->assertTrue( $controller->check_bearer( $this->request( [] ) ) );
	}

	/**
	 * An application password is the whole user, with no way to withhold one
	 * capability. So the scope that sends mail and takes payments must not ride
	 * in on it — that has to be asked for deliberately.
	 *
	 * @test
	 */
	public function an_application_password_never_carries_the_run_scope(): void {
		$GLOBALS['zaplane_test_app_password_uuid'] = 'e7f1c0aa-0000-4000-8000-000000000002';
		$GLOBALS['zaplane_test_caps']              = [ 'manage_options' ];
		$controller = new McpController();
		$request    = $this->request( [] );

		$this->assertTrue( $controller->check_bearer( $request ) );

		$tools = $this->tool_names( $controller, $request );
		$this->assertContains( 'list_apps', $tools );
		$this->assertContains( 'create_workflow', $tools );
		$this->assertNotContains( 'run_workflow', $tools );
	}

	/**
	 * Being signed in is not the same as presenting a credential on the request.
	 *
	 * @test
	 */
	public function a_merely_signed_in_administrator_is_not_let_in(): void {
		// Capable user, but no application password on the request and no bearer.
		$GLOBALS['zaplane_test_caps'] = [ 'manage_options' ];
		$controller                   = new McpController();

		$this->assertFalse( $controller->check_bearer( $this->request( [] ) ) );
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return array<int,string>
	 */
	private function tool_names( McpController $controller, $request ): array {
		$response = $controller->handle_rpc(
			$this->request( [ 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list' ] )
		);
		$data = $response instanceof \WP_REST_Response ? $response->get_data() : $response;

		return array_map( fn( $t ) => $t['name'], $data['result']['tools'] ?? [] );
	}

	/**
	 * A hosted connector is given nothing but this endpoint's URL. The pointer in
	 * the 401 is the only thing it can follow to find out that OAuth exists here.
	 *
	 * @test
	 */
	public function the_challenge_points_at_the_protected_resource_document(): void {
		$challenge = McpController::challenge();

		$this->assertStringStartsWith( 'Bearer ', $challenge );
		$this->assertStringContainsString( 'realm="Zaplane MCP"', $challenge );
		$this->assertStringContainsString(
			'resource_metadata="' . \Zaplane\Mcp\OAuth\Discovery::protected_resource_url() . '"',
			$challenge
		);
		$this->assertStringContainsString( '/.well-known/oauth-protected-resource', $challenge );
	}

	/**
	 * @test
	 */
	public function throttling_is_counted_once_per_request(): void {
		$issued     = TokenStore::issue( 'Client' );
		$controller = new McpController();

		// WordPress calls a route's permission_callback twice on a real HTTP
		// request — once to authorise, then again from rest_send_allow_header() —
		// so a counter that is not memoized charges two per call and halves the
		// limit. Two checks inside one request must cost one.
		$request = $this->request( [], $issued['token'] );

		$this->assertTrue( $controller->check_bearer( $request ) );
		$this->assertTrue(
			$controller->check_bearer( $request ),
			'The second check in the same request must not consume more quota.'
		);
	}

	/**
	 * @test
	 */
	public function it_reads_the_bearer_from_the_cgi_variable_when_the_header_was_stripped(): void {
		$issued     = TokenStore::issue( 'Client' );
		$controller = new McpController();

		// No Authorization header on the request — as Apache under CGI leaves it.
		$request = $this->request( [] );

		$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer ' . $issued['token'];

		try {
			$this->assertTrue( $controller->check_bearer( $request ) );
		} finally {
			unset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		}
	}

	/* ----------------------------- protocol ------------------------------- */

	/**
	 * @test
	 */
	public function initialize_announces_the_server_and_how_to_use_it(): void {
		$issued = TokenStore::issue( 'Client' );

		$response = $this->rpc(
			[
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'initialize',
			],
			$issued['token']
		);

		$this->assertSame( '2.0', $response['jsonrpc'] );
		$this->assertSame( 'Zaplane', $response['result']['serverInfo']['name'] );
		$this->assertArrayHasKey( 'tools', $response['result']['capabilities'] );
		$this->assertStringContainsString( 'search_capabilities', $response['result']['instructions'] );
	}

	/**
	 * @test
	 */
	public function an_unknown_method_is_a_protocol_error(): void {
		$issued = TokenStore::issue( 'Client' );

		$response = $this->rpc(
			[
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'resources/list',
			],
			$issued['token']
		);

		$this->assertSame( -32601, $response['error']['code'] );
	}

	/**
	 * @test
	 */
	public function a_notification_is_acknowledged_with_no_body(): void {
		$issued     = TokenStore::issue( 'Client' );
		$controller = new McpController();
		$request    = $this->request(
			[
				'jsonrpc' => '2.0',
				'method'  => 'notifications/initialized',
			],
			$issued['token']
		);

		$controller->check_bearer( $request );
		$response = $controller->handle_rpc( $request );

		$this->assertSame( 202, $response->get_status() );
		$this->assertNull( $response->get_data() );
	}

	/**
	 * @test
	 */
	public function a_batch_answers_exactly_the_messages_it_was_sent(): void {
		$issued     = TokenStore::issue( 'Client', TokenStore::ALL_SCOPES );
		$controller = new McpController();
		$request    = $this->request(
			[
				[
					'jsonrpc' => '2.0',
					'id'      => 1,
					'method'  => 'ping',
				],
				[
					'jsonrpc' => '2.0',
					'id'      => 2,
					'method'  => 'ping',
				],
			],
			$issued['token']
		);

		$controller->check_bearer( $request );
		$response = (array) $controller->handle_rpc( $request )->get_data();

		// Authenticating must not add anything to the body. Stashing the token via
		// set_param() used to append it to the decoded JSON array, so a two-message
		// batch came back with three responses, the third carrying the token's id.
		$this->assertCount( 2, $response );
		$this->assertSame( [ 1, 2 ], array_column( $response, 'id' ) );
	}

	/**
	 * @test
	 */
	public function a_batch_returns_one_result_per_message(): void {
		$issued = TokenStore::issue( 'Client' );

		$response = $this->rpc(
			[
				[
					'jsonrpc' => '2.0',
					'id'      => 1,
					'method'  => 'ping',
				],
				[
					'jsonrpc' => '2.0',
					'id'      => 2,
					'method'  => 'initialize',
				],
			],
			$issued['token']
		);

		$this->assertCount( 2, $response );
		$this->assertSame( 1, $response[0]['id'] );
		$this->assertSame( 2, $response[1]['id'] );
	}

	/* ------------------------------ scopes -------------------------------- */

	/**
	 * @test
	 */
	public function tools_list_shows_a_read_only_client_only_what_it_can_call(): void {
		$issued = TokenStore::issue( 'Reader', [ TokenStore::SCOPE_READ ] );

		$response = $this->rpc(
			[
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'tools/list',
			],
			$issued['token']
		);

		$names = array_column( $response['result']['tools'], 'name' );

		$this->assertContains( 'search_capabilities', $names );
		$this->assertNotContains( 'create_workflow', $names );
		$this->assertNotContains( 'run_workflow', $names );
	}

	/**
	 * @test
	 */
	public function calling_beyond_your_scope_is_refused_and_says_how_to_fix_it(): void {
		$issued = TokenStore::issue( 'Reader', [ TokenStore::SCOPE_READ ] );

		$response = $this->rpc(
			[
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'tools/call',
				'params'  => [
					'name'      => 'run_workflow',
					'arguments' => [ 'workflow_id' => 1 ],
				],
			],
			$issued['token']
		);

		$this->assertTrue( $response['result']['isError'] );
		$this->assertStringContainsString( '"run" scope', $this->toolText( $response ) );
	}

	/**
	 * @test
	 */
	public function a_tool_within_scope_runs(): void {
		$issued = TokenStore::issue( 'Reader', [ TokenStore::SCOPE_READ ] );

		$response = $this->rpc(
			[
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'tools/call',
				'params'  => [
					'name'      => 'search_capabilities',
					'arguments' => [ 'query' => 'send a slack message' ],
				],
			],
			$issued['token']
		);

		$this->assertArrayNotHasKey( 'isError', $response['result'] );
		$this->assertStringContainsString( 'slack', $this->toolText( $response ) );
	}

	/**
	 * @test
	 */
	public function an_unknown_tool_is_a_protocol_error(): void {
		$issued = TokenStore::issue( 'Client', TokenStore::ALL_SCOPES );

		$response = $this->rpc(
			[
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'tools/call',
				'params'  => [ 'name' => 'drop_database' ],
			],
			$issued['token']
		);

		$this->assertSame( -32602, $response['error']['code'] );
	}

	/**
	 * @test
	 */
	public function a_failing_tool_reports_back_as_a_tool_error_not_a_crash(): void {
		$issued = TokenStore::issue( 'Client', TokenStore::ALL_SCOPES );

		$response = $this->rpc(
			[
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'tools/call',
				'params'  => [
					'name'      => 'describe_app',
					'arguments' => [ 'slug' => 'not-a-real-app' ],
				],
			],
			$issued['token']
		);

		$this->assertTrue( $response['result']['isError'] );
		$this->assertStringContainsString( 'Unknown app', $this->toolText( $response ) );
	}

	/* --------------------------- self-reference --------------------------- */

	/**
	 * @test
	 */
	public function it_refuses_to_run_a_workflow_for_a_call_from_this_same_site(): void {
		$issued = TokenStore::issue( 'Client', TokenStore::ALL_SCOPES );

		$response = $this->rpc(
			[
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'tools/call',
				'params'  => [
					'name'      => 'run_workflow',
					'arguments' => [ 'workflow_id' => 1 ],
				],
			],
			$issued['token'],
			[ 'x-zaplane-origin' => home_url() ]
		);

		$this->assertTrue( $response['result']['isError'] );
		$this->assertStringContainsString( 're-enter', $this->toolText( $response ) );
	}

	/**
	 * @test
	 */
	public function a_call_from_this_same_site_can_still_read(): void {
		$issued = TokenStore::issue( 'Client', TokenStore::ALL_SCOPES );

		$response = $this->rpc(
			[
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'tools/call',
				'params'  => [
					'name'      => 'search_capabilities',
					'arguments' => [ 'query' => 'slack message' ],
				],
			],
			$issued['token'],
			[ 'x-zaplane-origin' => home_url() ]
		);

		$this->assertArrayNotHasKey( 'isError', $response['result'] );
	}

	/**
	 * @test
	 */
	public function a_call_from_a_different_site_is_not_treated_as_a_loop(): void {
		$issued = TokenStore::issue( 'Client', [ TokenStore::SCOPE_READ ] );

		$response = $this->rpc(
			[
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'tools/list',
			],
			$issued['token'],
			[ 'x-zaplane-origin' => 'https://some-other-site.example' ]
		);

		$this->assertNotEmpty( $response['result']['tools'] );
	}
}
