<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Trello;
use Zaplane\Tests\Mocks\WPDBMock;

class TrelloTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Trello::class;
	}

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb = new WPDBMock();
	}

	public function test_slug(): void {
		$this->assertEquals( 'trello', Trello::get_slug() );
	}

	public function test_name(): void {
		$this->assertEquals( 'Trello', Trello::get_name() );
	}

	public function test_requires_connection(): void {
		$this->assertTrue( Trello::requires_connection() );
	}

	public function test_auth_type(): void {
		$this->assertEquals( 'token_key', Trello::get_auth_type() );
	}

	public function test_auth_fields(): void {
		$fields = Trello::get_auth_fields();

		$this->assertArrayHasKey( 'api_key', $fields );
		$this->assertArrayHasKey( 'token', $fields );

		$this->assertTrue( $fields['api_key']['required'] );
		$this->assertTrue( $fields['token']['required'] );
	}

	public function test_actions_exist(): void {
		$actions = Trello::get_actions();

		$this->assertArrayHasKey( 'create_card', $actions );
		$this->assertArrayHasKey( 'get_card', $actions );
		$this->assertArrayHasKey( 'update_card', $actions );
		$this->assertArrayHasKey( 'delete_card', $actions );
		$this->assertArrayHasKey( 'create_board', $actions );
		$this->assertArrayHasKey( 'create_label', $actions );
		$this->assertArrayHasKey( 'create_checklist', $actions );
	}

	public function test_invalid_connection(): void {
		$result = Trello::test_connection(
			[
				'api_key' => '',
				'token'   => '',
			]
		);

		$this->assertFalse( $result['success'] );
		$this->assertEquals(
			'api_key and token are required.',
			$result['message']
		);
	}

	public function test_dynamic_queries(): void {
		$queries = Trello::get_dynamic_queries();

		$this->assertArrayHasKey( 'board_query', $queries );
		$this->assertArrayHasKey( 'list_query', $queries );
		$this->assertArrayHasKey( 'card_query', $queries );
		$this->assertArrayHasKey( 'label_query', $queries );
	}
}
