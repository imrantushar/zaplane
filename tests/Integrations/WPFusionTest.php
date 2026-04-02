<?php

namespace {
	require_once dirname( __DIR__, 2 ) . '/integrations/wp-fusion.php';
	require_once __DIR__ . '/Support/WPFusionTestStubs.php';
}

namespace Zaplane\Tests\Integrations {

use Zaplane\Integrations\WpFusion;

class WPFusionTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return WpFusion::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();

		\WPFusionTestDouble::reset();

		$instance = \WPFusionTestDouble::instance();
		$instance->settings->available_tags = [
			'101' => 'VIP',
			'202' => 'Customer',
			'303' => 'Lead',
		];
		$instance->setOption( 'available_tags', $instance->settings->available_tags );

		$instance->user->contact_ids = [
			'1' => 'cid_1',
			'2' => 'cid_2',
		];
		$instance->user->user_ids = [
			'cid_1' => 1,
			'cid_2' => 2,
		];

		$instance->crm->contacts = [
			'cid_2' => [
				'user_email' => 'test@example.com',
				'first_name' => 'Test',
				'last_name'  => 'User',
			],
		];
		$instance->crm->contacts_by_email = [
			'test@example.com' => 'cid_2',
		];
	}

	protected function getTriggerTests(): array {
		return [
			'tags_applied' => [ 2, [ '101', '202' ] ],
			'tags_removed' => [ 2, [ '202' ] ],
			'user_imported' => [ 2, [ 'user_email' => 'test@example.com' ] ],
			'guest_contact_created' => [ 'cid_2', 'test@example.com' ],
			'guest_contact_updated' => [ 'cid_2', 'test@example.com' ],
		];
	}

	protected function getActionTests(): array {
		return [
			'apply_tags' => [
				'user_id' => 2,
				'tags'    => '101,202',
			],
			'remove_tags' => [
				'user_id' => 2,
				'tags'    => '202',
			],
			'get_contact_id' => [
				'user_id' => 2,
			],
			'get_user_id' => [
				'contact_id' => 'cid_2',
			],
			'import_user' => [
				'contact_id'        => 'cid_2',
				'role'              => 'subscriber',
				'send_notification' => true,
			],
			'create_or_update_contact' => [
				'email'      => 'new@example.com',
				'first_name' => 'New',
				'last_name'  => 'Contact',
			],
		];
	}

	public function test_tags_trigger_can_filter_by_selected_tag(): void {
		$result = WpFusion::resolve_trigger(
			$this->makeTriggerNode( 'tags_applied', [ 'tag_id' => '101' ] ),
			[ 2, [ '101', '202' ] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( '101', $result['matched_tag_id'] );
		$this->assertEquals( [ 'VIP', 'Customer' ], $result['tag_labels'] );
	}

	public function test_tags_trigger_returns_false_when_selected_tag_does_not_match(): void {
		$result = WpFusion::resolve_trigger(
			$this->makeTriggerNode( 'tags_applied', [ 'tag_id' => '303' ] ),
			[ 2, [ '101', '202' ] ]
		);

		$this->assertFalse( $result );
	}

	public function test_apply_tags_maps_labels_to_tag_ids(): void {
		$result = WpFusion::execute_node(
			$this->makeActionNode( 'apply_tags', [
				'user_id' => 2,
				'tags'    => 'VIP, Customer',
			] ),
			[]
		);

		$this->assertEquals( [ '101', '202' ], $result['data']['tags'] );
		$this->assertEquals( [ 'VIP', 'Customer' ], $result['data']['tag_labels'] );
	}

	public function test_tag_actions_use_dynamic_multi_select_schema(): void {
		$schema = WpFusion::get_action_config_schema( 'apply_tags' );

		$this->assertSame( 'tags', $schema[1]['key'] );
		$this->assertSame( 'select', $schema[1]['type'] );
		$this->assertTrue( $schema[1]['multiple'] );
		$this->assertSame( 'wpfusion', $schema[1]['dynamic']['integration'] );
		$this->assertSame( 'tags_query', $schema[1]['dynamic']['query'] );
		$this->assertSame( [ 'value', 'label' ], $schema[1]['dynamic']['select'] );
	}

	public function test_create_or_update_contact_updates_existing_contact(): void {
		$instance = \WPFusionTestDouble::instance();

		$result = WpFusion::execute_node(
			$this->makeActionNode( 'create_or_update_contact', [
				'email'      => 'test@example.com',
				'first_name' => 'Updated',
			] ),
			[]
		);

		$this->assertEquals( 'updated', $result['data']['mode'] );
		$this->assertEquals( 'cid_2', $result['data']['contact_id'] );
		$this->assertSame( 'cid_2', $instance->crm->last_update['contact_id'] );
		$this->assertSame( 'test@example.com', $instance->crm->last_update['data']['user_email'] );
		$this->assertArrayNotHasKey( 'email', $instance->crm->last_update['data'] );
	}

	public function test_create_or_update_contact_can_resolve_existing_contact_from_user_id(): void {
		$instance = \WPFusionTestDouble::instance();

		$result = WpFusion::execute_node(
			$this->makeActionNode( 'create_or_update_contact', [
				'user_id'    => 2,
				'first_name' => 'Updated',
			] ),
			[]
		);

		$this->assertEquals( 'updated', $result['data']['mode'] );
		$this->assertEquals( 'cid_2', $result['data']['contact_id'] );
		$this->assertSame( 'cid_2', $instance->crm->last_update['contact_id'] );
		$this->assertSame( 'test@example.com', $instance->crm->last_update['data']['user_email'] );
	}

	public function test_query_tags_returns_any_option_and_matches_search(): void {
		$result = WpFusion::query_tags( [ 'search' => 'vip' ] );

		$this->assertEquals( 'any', $result[0]['value'] );
		$this->assertCount( 2, $result );
		$this->assertEquals( '101', $result[1]['value'] );
	}

	public function test_get_user_id_action_falls_back_to_saved_contact_mapping(): void {
		$instance = \WPFusionTestDouble::instance();
		unset( $instance->user );

		global $wpdb;
		$wpdb->usermeta = 'wp_usermeta';
		$wpdb->tables['var'] = 25;

		$result = WpFusion::execute_node(
			$this->makeActionNode( 'get_user_id', [ 'contact_id' => 'cid_lookup' ] ),
			[]
		);

		$this->assertSame( 25, $result['data']['user_id'] );
	}

	public function test_get_contact_id_action_fails_with_clear_message_when_no_mapping_exists(): void {
		$instance = \WPFusionTestDouble::instance();
		unset( $instance->user );

		global $wpdb;
		$wpdb->usermeta = 'wp_usermeta';
		$wpdb->tables['var'] = null;

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'WP Fusion is not fully configured, and no saved contact ID mapping was found for this user.' );

		WpFusion::execute_node(
			$this->makeActionNode( 'get_contact_id', [ 'user_id' => 2 ] ),
			[]
		);
	}
}
}
