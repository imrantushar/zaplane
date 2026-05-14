<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Gmail;

class GmailTest extends IntegrationTestCase {

	private array $credentials = [
		'access_token' => 'ya29.test_access_token_abc123',
	];

	protected function getIntegrationClass(): string {
		return Gmail::class;
	}

	// ========== CONTRACT TESTS (inherited automatically) ==========
	// - integration_has_slug
	// - all_triggers_have_labels_and_hooks     (no triggers — passes vacuously)
	// - all_actions_have_labels
	// - trigger_config_schemas_are_valid       (no triggers — passes vacuously)
	// - action_config_schemas_are_valid
	// - output_ports_are_valid

	// ========== ACTION: send_email ==========

	public function test_send_email_succeeds(): void {
		$this->mockHttp( [
			'id'       => 'msg_abc123',
			'threadId' => 'thread_xyz456',
			'labelIds' => [ 'SENT' ],
		] );

		$node   = $this->makeActionNode( 'send_email', [
			'to'      => 'recipient@example.com',
			'subject' => 'Hello from Zaplane',
			'body'    => 'This is the email body.',
		], $this->credentials );
		$result = Gmail::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'msg_abc123', $result['data']['gmail_message_id'] );
		$this->assertEquals( 'thread_xyz456', $result['data']['gmail_thread_id'] );
		$this->assertEquals( 'sent', $result['data']['gmail_status'] );
	}

	public function test_send_email_throws_without_credentials(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/credentials/' );

		$node = $this->makeActionNode( 'send_email', [ 'to' => 'a@b.com', 'subject' => 'Hi', 'body' => 'Test' ] );
		Gmail::execute_node( $node, [] );
	}

	public function test_send_email_throws_without_to(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/recipient/' );

		$node = $this->makeActionNode( 'send_email', [ 'subject' => 'Hi', 'body' => 'Test' ], $this->credentials );
		Gmail::execute_node( $node, [] );
	}

	public function test_send_email_throws_without_subject(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/subject/' );

		$node = $this->makeActionNode( 'send_email', [ 'to' => 'a@b.com', 'body' => 'Test' ], $this->credentials );
		Gmail::execute_node( $node, [] );
	}

	public function test_send_email_throws_without_body(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/body/' );

		$node = $this->makeActionNode( 'send_email', [ 'to' => 'a@b.com', 'subject' => 'Hi' ], $this->credentials );
		Gmail::execute_node( $node, [] );
	}

	public function test_send_email_throws_on_api_error(): void {
		$this->mockHttp( [
			'error' => [
				'code'    => 403,
				'message' => 'Request had insufficient authentication scopes',
			],
		], 403 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/insufficient authentication/' );

		$node = $this->makeActionNode( 'send_email', [ 'to' => 'a@b.com', 'subject' => 'Hi', 'body' => 'Test' ], $this->credentials );
		Gmail::execute_node( $node, [] );
	}

	public function test_send_email_supports_cc_and_bcc(): void {
		$this->mockHttp( [
			'id'       => 'msg_cc123',
			'threadId' => 'thread_cc456',
			'labelIds' => [ 'SENT' ],
		] );

		$node   = $this->makeActionNode( 'send_email', [
			'to'      => 'recipient@example.com',
			'cc'      => 'cc@example.com',
			'bcc'     => 'bcc@example.com',
			'subject' => 'With CC and BCC',
			'body'    => 'Body text.',
		], $this->credentials );
		$result = Gmail::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'msg_cc123', $result['data']['gmail_message_id'] );
	}

	public function test_send_email_supports_html_body(): void {
		$this->mockHttp( [
			'id'       => 'msg_html123',
			'threadId' => 'thread_html456',
			'labelIds' => [ 'SENT' ],
		] );

		$node   = $this->makeActionNode( 'send_email', [
			'to'         => 'recipient@example.com',
			'subject'    => 'HTML email',
			'body'       => '<h1>Hello</h1><p>World</p>',
			'content_type' => 'text/html',
		], $this->credentials );
		$result = Gmail::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
	}

	// ========== ACTION: send_reply ==========

	public function test_send_reply_succeeds(): void {
		$this->mockHttp( [
			'id'       => 'msg_reply123',
			'threadId' => 'thread_xyz456',
			'labelIds' => [ 'SENT' ],
		] );

		$node   = $this->makeActionNode( 'send_reply', [
			'to'        => 'sender@example.com',
			'subject'   => 'Re: Original Subject',
			'body'      => 'Thanks for your email!',
			'thread_id' => 'thread_xyz456',
			'message_id_header' => '<original-message-id@gmail.com>',
		], $this->credentials );
		$result = Gmail::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'msg_reply123', $result['data']['gmail_message_id'] );
		$this->assertEquals( 'thread_xyz456', $result['data']['gmail_thread_id'] );
	}

	public function test_send_reply_throws_without_thread_id(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/thread_id/' );

		$node = $this->makeActionNode( 'send_reply', [
			'to'      => 'a@b.com',
			'subject' => 'Re: Test',
			'body'    => 'Reply body',
		], $this->credentials );
		Gmail::execute_node( $node, [] );
	}

	// ========== ACTION: create_draft ==========

	public function test_create_draft_succeeds(): void {
		$this->mockHttp( [
			'id'      => 'draft_abc123',
			'message' => [
				'id'       => 'msg_draft456',
				'threadId' => 'thread_draft789',
			],
		] );

		$node   = $this->makeActionNode( 'create_draft', [
			'to'      => 'recipient@example.com',
			'subject' => 'Draft email',
			'body'    => 'Draft body text.',
		], $this->credentials );
		$result = Gmail::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'draft_abc123', $result['data']['gmail_draft_id'] );
		$this->assertEquals( 'msg_draft456', $result['data']['gmail_message_id'] );
		$this->assertEquals( 'thread_draft789', $result['data']['gmail_thread_id'] );
	}

	public function test_create_draft_throws_without_to(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/recipient/' );

		$node = $this->makeActionNode( 'create_draft', [ 'subject' => 'Draft', 'body' => 'Test' ], $this->credentials );
		Gmail::execute_node( $node, [] );
	}

	// ========== ACTION: add_label ==========

	public function test_add_label_succeeds(): void {
		$this->mockHttp( [
			'id'       => 'msg_abc123',
			'threadId' => 'thread_xyz456',
			'labelIds' => [ 'INBOX', 'Label_123', 'IMPORTANT' ],
		] );

		$node   = $this->makeActionNode( 'add_label', [
			'message_id' => 'msg_abc123',
			'label_ids'  => 'Label_123,IMPORTANT',
		], $this->credentials );
		$result = Gmail::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'msg_abc123', $result['data']['gmail_message_id'] );
		$this->assertContains( 'IMPORTANT', $result['data']['gmail_label_ids'] );
	}

	public function test_add_label_throws_without_message_id(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/message_id/' );

		$node = $this->makeActionNode( 'add_label', [ 'label_ids' => 'Label_123' ], $this->credentials );
		Gmail::execute_node( $node, [] );
	}

	public function test_add_label_throws_without_label_ids(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/label/' );

		$node = $this->makeActionNode( 'add_label', [ 'message_id' => 'msg_abc123' ], $this->credentials );
		Gmail::execute_node( $node, [] );
	}

	// ========== ACTION: remove_label ==========

	public function test_remove_label_succeeds(): void {
		$this->mockHttp( [
			'id'       => 'msg_abc123',
			'threadId' => 'thread_xyz456',
			'labelIds' => [ 'INBOX' ],
		] );

		$node   = $this->makeActionNode( 'remove_label', [
			'message_id' => 'msg_abc123',
			'label_ids'  => 'UNREAD',
		], $this->credentials );
		$result = Gmail::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'msg_abc123', $result['data']['gmail_message_id'] );
		$this->assertIsArray( $result['data']['gmail_label_ids'] );
	}

	public function test_remove_label_throws_without_message_id(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/message_id/' );

		$node = $this->makeActionNode( 'remove_label', [ 'label_ids' => 'UNREAD' ], $this->credentials );
		Gmail::execute_node( $node, [] );
	}

	// ========== WP_Error throws ==========

	public function test_wp_error_throws_exception(): void {
		// No HTTP response queued → wp_remote_post returns WP_Error
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/request failed|Mock/' );

		$node = $this->makeActionNode( 'send_email', [ 'to' => 'a@b.com', 'subject' => 'Hi', 'body' => 'Test' ], $this->credentials );
		Gmail::execute_node( $node, [] );
	}

	// ========== Unknown action passthrough ==========

	public function test_unknown_action_returns_passthrough(): void {
		$node   = $this->makeActionNode( 'nonexistent_action', [], $this->credentials );
		$result = Gmail::execute_node( $node, [ 'foo' => 'bar' ] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( [ 'foo' => 'bar' ], $result['data'] );
	}

	// ========== test_connection ==========

	public function test_connection_succeeds(): void {
		$this->mockHttp( [
			'emailAddress'  => 'user@gmail.com',
			'messagesTotal' => 150,
			'threadsTotal'  => 75,
		] );

		$result = Gmail::test_connection( $this->credentials );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'user@gmail.com', $result['message'] );
		$this->assertEquals( 'user@gmail.com', $result['details']['email'] );
	}

	public function test_connection_fails_without_access_token(): void {
		$result = Gmail::test_connection( [] );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'access_token', $result['message'] );
	}

	public function test_connection_fails_on_api_error(): void {
		$this->mockHttp( [
			'error' => [
				'code'    => 401,
				'message' => 'Invalid Credentials',
			],
		], 401 );

		$result = Gmail::test_connection( $this->credentials );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Invalid Credentials', $result['message'] );
	}

	// ========== Dynamic query: gmail_labels ==========

	public function test_dynamic_query_gmail_labels_returns_label_list(): void {
		$this->mockHttp( [
			'labels' => [
				[ 'id' => 'INBOX',     'name' => 'INBOX' ],
				[ 'id' => 'SENT',      'name' => 'SENT' ],
				[ 'id' => 'Label_123', 'name' => 'My Custom Label' ],
			],
		] );

		$queries = Gmail::get_dynamic_queries();
		$this->assertArrayHasKey( 'gmail_labels', $queries );

		$result = call_user_func( $queries['gmail_labels'], [
			'credentials' => $this->credentials,
		] );

		$this->assertCount( 3, $result );
		$this->assertEquals( 'INBOX', $result[0]['value'] );
		$this->assertEquals( 'INBOX', $result[0]['label'] );
		$this->assertEquals( 'Label_123', $result[2]['value'] );
		$this->assertEquals( 'My Custom Label', $result[2]['label'] );
	}

	public function test_dynamic_query_gmail_labels_returns_empty_on_missing_credentials(): void {
		$queries = Gmail::get_dynamic_queries();
		$result  = call_user_func( $queries['gmail_labels'], [] );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	// ========== No triggers ==========

	public function test_has_no_triggers(): void {
		$this->assertEmpty( Gmail::get_triggers() );
	}
}
