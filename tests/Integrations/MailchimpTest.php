<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Mailchimp;

class MailchimpTest extends IntegrationTestCase {

	private array $credentials = [ 'api_key' => 'test-us1' ];

	protected function getIntegrationClass(): string {
		return Mailchimp::class;
	}

	public function test_upsert_subscriber_succeeds_with_tags(): void {
		$this->mockHttp( [
			'id'            => 'abc123',
			'email_address' => 'user@example.com',
			'status'        => 'subscribed',
		] );
		$this->mockHttp( [ 'ok' => true ] );

		$result = Mailchimp::execute_node(
			$this->makeActionNode( 'upsert_subscriber', [
				'list_id'                  => 'list_1',
				'email'                    => 'user@example.com',
				'subscriber_status_if_new' => 'mailchimp_subscribed',
				'tags'                     => 'vip, webinar',
			], $this->credentials ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'list_1', $result['data']['mailchimp_list_id'] );
		$this->assertEquals( 'user@example.com', $result['data']['mailchimp_email'] );
		$this->assertEquals( 'subscribed', $result['data']['mailchimp_status'] );
		$this->assertEquals( 'abc123', $result['data']['mailchimp_id'] );
	}

	public function test_upsert_subscriber_throws_without_valid_email(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'A valid email address is required' );

		Mailchimp::execute_node(
			$this->makeActionNode( 'upsert_subscriber', [
				'list_id' => 'list_1',
				'email'   => 'not-an-email',
			], $this->credentials ),
			[]
		);
	}

	public function test_unsubscribe_subscriber_succeeds(): void {
		$this->mockHttp( [
			'id'            => 'abc123',
			'email_address' => 'user@example.com',
			'status'        => 'unsubscribed',
		] );

		$result = Mailchimp::execute_node(
			$this->makeActionNode( 'unsubscribe_subscriber', [
				'list_id' => 'list_1',
				'email'   => 'user@example.com',
			], $this->credentials ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'unsubscribed', $result['data']['mailchimp_status'] );
	}

	public function test_unsubscribe_subscriber_throws_without_valid_email(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'A valid email address is required' );

		Mailchimp::execute_node(
			$this->makeActionNode( 'unsubscribe_subscriber', [
				'list_id' => 'list_1',
				'email'   => 'bad-email',
			], $this->credentials ),
			[]
		);
	}

	public function test_add_tags_succeeds(): void {
		$this->mockHttp( [ 'ok' => true ] );

		$result = Mailchimp::execute_node(
			$this->makeActionNode( 'add_tags', [
				'list_id' => 'list_1',
				'email'   => 'user@example.com',
				'tags'    => 'vip, webinar',
			], $this->credentials ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'active', $result['data']['mailchimp_tag_status'] );
		$this->assertEquals( [ 'vip', 'webinar' ], $result['data']['mailchimp_tags'] );
	}

	public function test_add_tags_throws_without_tags(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'At least one tag is required' );

		Mailchimp::execute_node(
			$this->makeActionNode( 'add_tags', [
				'list_id' => 'list_1',
				'email'   => 'user@example.com',
				'tags'    => '',
			], $this->credentials ),
			[]
		);
	}

	public function test_remove_tags_succeeds(): void {
		$this->mockHttp( [ 'ok' => true ] );

		$result = Mailchimp::execute_node(
			$this->makeActionNode( 'remove_tags', [
				'list_id' => 'list_1',
				'email'   => 'user@example.com',
				'tags'    => 'vip',
			], $this->credentials ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'inactive', $result['data']['mailchimp_tag_status'] );
		$this->assertEquals( [ 'vip' ], $result['data']['mailchimp_tags'] );
	}

	public function test_remove_tags_throws_without_tags(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'At least one tag is required' );

		Mailchimp::execute_node(
			$this->makeActionNode( 'remove_tags', [
				'list_id' => 'list_1',
				'email'   => 'user@example.com',
				'tags'    => '',
			], $this->credentials ),
			[]
		);
	}

	public function test_archive_subscriber_succeeds(): void {
		$this->mockHttp( [ 'deleted' => true ] );

		$result = Mailchimp::execute_node(
			$this->makeActionNode( 'archive_subscriber', [
				'list_id' => 'list_1',
				'email'   => 'user@example.com',
			], $this->credentials ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'archived', $result['data']['mailchimp_status'] );
	}

	public function test_archive_subscriber_throws_without_valid_email(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'A valid email address is required' );

		Mailchimp::execute_node(
			$this->makeActionNode( 'archive_subscriber', [
				'list_id' => 'list_1',
				'email'   => 'bad-email',
			], $this->credentials ),
			[]
		);
	}

	public function test_query_lists_returns_filtered_results(): void {
		$this->mockHttp( [
			'lists' => [
				[ 'id' => 'list_1', 'name' => 'Newsletter' ],
				[ 'id' => 'list_2', 'name' => 'Customers' ],
			],
		] );

		$result = Mailchimp::query_lists( [
			'api_key' => 'test-us1',
			'search'  => 'news',
			'limit'   => 10,
		] );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'list_1', $result[0]['id'] );
	}

	public function test_connection_succeeds(): void {
		$this->mockHttp( [
			'account_name' => 'Zaplane Mailchimp',
			'account_id'   => 'acc_1',
		] );

		$result = Mailchimp::test_connection( [ 'api_key' => 'test-us1' ] );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'Zaplane Mailchimp', $result['message'] );
	}

	public function test_connection_fails_without_api_key(): void {
		$result = Mailchimp::test_connection( [] );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'API key is required', $result['message'] );
	}
}
