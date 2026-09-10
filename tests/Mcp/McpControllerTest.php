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
