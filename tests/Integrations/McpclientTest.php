<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Mcpclient;
use Zaplane\Tests\WPMocks;

/**
 * MCP Client: handshake, then list tools or call one on a remote MCP server.
 */
class McpclientTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Mcpclient::class;
	}

	private function node( string $event, array $config = [] ): array {
		return $this->makeActionNode( $event, array_merge( [ 'server_url' => 'https://mcp.example.com/mcp' ], $config ) );
	}

	/** The initialize reply and the "initialized" notification's reply. */
	private function queue_handshake(): void {
		WPMocks::setHttpResponse( [ 'jsonrpc' => '2.0', 'id' => 1, 'result' => [ 'protocolVersion' => '2025-03-26' ] ], 200, [ 'Mcp-Session-Id' => 'sess-1' ] );
		WPMocks::setHttpRawResponse( '', 202 );
	}

	public function test_needs_a_server(): void {
		$out = Mcpclient::execute_node( $this->makeActionNode( 'list_tools', [] ), [ 'keep' => 1 ] );
		$this->assertFalse( $out['data']['success'] );
		$this->assertSame( 1, $out['data']['keep'] );
	}

	public function test_lists_tools(): void {
		$this->queue_handshake();
		WPMocks::setHttpResponse( [ 'jsonrpc' => '2.0', 'id' => 2, 'result' => [ 'tools' => [ [ 'name' => 'search' ] ] ] ] );
		$out = Mcpclient::execute_node( $this->node( 'list_tools' ), [] );
		$this->assertTrue( $out['data']['success'] );
		$this->assertSame( 'search', $out['data']['tools'][0]['name'] );
	}

	public function test_reads_the_reply_out_of_an_event_stream_with_notifications_first(): void {
		$this->queue_handshake();
		WPMocks::setHttpRawResponse(
			"event: message\ndata: {\"jsonrpc\":\"2.0\",\"method\":\"notifications/progress\",\"params\":{\"progress\":1}}\n\n"
			. "event: message\ndata: {\"jsonrpc\":\"2.0\",\"id\":2,\"result\":{\"content\":[{\"type\":\"text\",\"text\":\"42 orders\"}]}}\n\n"
		);
		$out = Mcpclient::execute_node( $this->node( 'call_tool', [ 'tool_name' => 'count_orders', 'arguments' => '{"status":"paid"}' ] ), [] );
		$this->assertTrue( $out['data']['success'] );
		$this->assertSame( '42 orders', $out['data']['result'] );
	}

	public function test_tool_errors_and_unreadable_replies_fail(): void {
		$this->queue_handshake();
		WPMocks::setHttpResponse( [ 'jsonrpc' => '2.0', 'id' => 2, 'error' => [ 'code' => -32602, 'message' => 'Unknown tool' ] ] );
		$out = Mcpclient::execute_node( $this->node( 'call_tool', [ 'tool_name' => 'nope' ] ), [] );
		$this->assertFalse( $out['data']['success'] );
		$this->assertSame( 'Unknown tool', $out['data']['error'] );

		$this->queue_handshake();
		WPMocks::setHttpRawResponse( '<html>Bad gateway</html>', 502 );
		$out = Mcpclient::execute_node( $this->node( 'list_tools' ), [] );
		$this->assertFalse( $out['data']['success'], 'a page that is not an MCP reply is not "no tools"' );
		$this->assertStringContainsString( '502', $out['data']['error'] );
	}

	public function test_call_tool_needs_a_tool_name(): void {
		$this->queue_handshake();
		$out = Mcpclient::execute_node( $this->node( 'call_tool' ), [] );
		$this->assertFalse( $out['data']['success'] );
		$this->assertSame( 'tool_name is required.', $out['data']['error'] );
	}

	public function test_only_http_addresses_are_called(): void {
		$out = Mcpclient::execute_node( $this->node( 'list_tools', [ 'server_url' => 'file:///etc/passwd' ] ), [] );
		$this->assertFalse( $out['data']['success'] );
		$this->assertStringContainsString( 'initialize failed', $out['data']['error'] );
	}
}
