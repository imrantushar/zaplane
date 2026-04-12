<?php

namespace {
	require_once __DIR__ . '/../mocks/woomemberships.php';
}

namespace Zaplane\Tests\Integrations {

use Zaplane\Integrations\WooMemberships;

class WooMembershipsTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return WooMemberships::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();
		\WooMembershipsTestStore::reset();
	}

	protected function getTriggerTests(): array {
		return [
			'membership_saved' => [
				(object) [ 'id' => 901 ],
				[
					'user_membership_id' => 2101,
					'user_id' => 701,
					'is_update' => true,
				],
			],
			'membership_created' => [
				(object) [ 'id' => 901 ],
				[
					'user_membership_id' => 2101,
					'user_id' => 701,
					'is_update' => false,
				],
			],
			'membership_cancelled' => [ 2101 ],
			'membership_status_changed' => [ new \WC_Memberships_User_Membership( 2101 ), 'active', 'paused' ],
			'membership_note_added' => [
				[
					'user_membership_id' => 2101,
					'note' => (object) [ 'comment_content' => 'Membership note' ],
					'notify' => true,
				],
			],
		];
	}

	protected function getActionTests(): array {
		return [
			'create_membership' => [
				'plan_id' => 903,
				'user_id' => 703,
				'product_id' => 903,
				'order_id' => 503,
				'membership_status' => 'wcm-active',
			],
			'get_memberships_all' => [
				'user_id' => 701,
				'membership_status' => 'wcm-active',
			],
			'get_membership_single' => [
				'membership_id' => 2101,
			],
			'update_membership_status' => [
				'membership_id' => 2102,
				'membership_status' => 'wcm-active',
				'note' => 'Updated by automation',
			],
			'cancel_membership' => [
				'membership_id' => 2101,
				'note' => 'Cancelled by automation',
			],
			'pause_membership' => [
				'membership_id' => 2101,
				'note' => 'Paused by automation',
			],
			'activate_membership' => [
				'membership_id' => 2102,
				'note' => 'Activated by automation',
			],
			'add_membership_note' => [
				'membership_id' => 2101,
				'note' => 'Manual follow-up',
				'notify_member' => true,
			],
		];
	}

	public function test_trigger_membership_status_changed_returns_old_and_new_status(): void {
		$result = WooMemberships::resolve_trigger(
			$this->makeTriggerNode( 'membership_status_changed' ),
			[ new \WC_Memberships_User_Membership( 2101 ), 'active', 'paused' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 2101, $result['membership_id'] );
		$this->assertEquals( 'active', $result['old_status'] );
		$this->assertEquals( 'paused', $result['new_status'] );
	}

	public function test_trigger_returns_false_for_invalid_payload(): void {
		$result = WooMemberships::resolve_trigger(
			$this->makeTriggerNode( 'membership_saved' ),
			[]
		);

		$this->assertFalse( $result );
	}

	public function test_trigger_membership_saved_does_not_use_plan_id_as_membership_id(): void {
		$result = WooMemberships::resolve_trigger(
			$this->makeTriggerNode( 'membership_saved' ),
			[
				(object) [ 'id' => 2101 ],
				[],
			]
		);

		$this->assertFalse( $result );
	}

	public function test_action_get_membership_single_returns_membership_payload(): void {
		$result = WooMemberships::execute_node(
			$this->makeActionNode( 'get_membership_single', [ 'membership_id' => 2101 ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 2101, $result['data']['membership']['membership_id'] );
	}

	public function test_action_update_membership_status_updates_membership(): void {
		$result = WooMemberships::execute_node(
			$this->makeActionNode( 'update_membership_status', [
				'membership_id' => 2102,
				'membership_status' => 'wcm-active',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'active', $result['data']['membership']['new_status'] );
	}

	public function test_execute_node_supports_legacy_action_node_shape(): void {
		$result = WooMemberships::execute_node(
			[
				'type' => 'action',
				'config' => [
					'action' => 'get_membership_single',
					'data' => [
						'membership_id' => 2101,
					],
				],
			],
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 2101, $result['data']['membership']['membership_id'] );
	}

	public function test_execute_node_supports_top_level_event_key(): void {
		$result = WooMemberships::execute_node(
			[
				'type' => 'action',
				'event' => 'get_membership_single',
				'data' => [
					'config' => [
						'membership_id' => 2101,
					],
				],
			],
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 2101, $result['data']['membership']['membership_id'] );
	}
}
}
