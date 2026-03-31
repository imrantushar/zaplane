<?php

namespace Zaplane\Tests\Integrations {

	use Zaplane\Integrations\ActiveCampaign;
	use Zaplane\Tests\WPMocks;

	class ActiveCampaignTest extends IntegrationTestCase {

		private array $credentials = [
			'api_url' => 'https://zaplane.api-us1.com',
			'api_key' => 'test-api-key',
		];

		protected function getIntegrationClass(): string {
			return ActiveCampaign::class;
		}

		protected function setupMockData(): void {
			parent::setupMockData();

			WPMocks::setOption(
				'settings_activecampaign',
				[
					'api_url' => 'https://zaplane.api-us1.com',
					'api_key' => 'plugin-settings-key',
				]
			);
		}

		protected function tearDown(): void {
			$_POST   = [];
			$_GET    = [];
			$_SERVER = [];
			parent::tearDown();
		}

		protected function getTriggerTests(): array {
			return [];
		}

		public function test_upsert_contact_uses_plugin_settings_fallback(): void {
			$this->mockHttp(
				[
					'contact' => [
						'id'        => '101',
						'email'     => 'user@example.com',
						'firstName' => 'John',
						'lastName'  => 'Doe',
					],
				]
			);

			$result = ActiveCampaign::execute_node(
				$this->makeActionNode(
					'upsert_contact',
					[
						'email'      => 'user@example.com',
						'first_name' => 'John',
						'last_name'  => 'Doe',
					]
				),
				[]
			);

			$this->assertEquals( 'main', $result['port'] );
			$this->assertEquals( '101', $result['data']['activecampaign_contact_id'] );
			$this->assertEquals( 'user@example.com', $result['data']['activecampaign_contact_email'] );
		}

		public function test_add_contact_to_list_subscribes_contact(): void {
			$this->mockHttp(
				[
					'contact' => [
						'id'    => '101',
						'email' => 'user@example.com',
					],
				]
			);
			$this->mockHttp(
				[
					'contactList' => [
						'id'      => '9001',
						'contact' => '101',
						'list'    => '12',
						'status'  => '1',
					],
				]
			);

			$result = ActiveCampaign::execute_node(
				$this->makeActionNode(
					'add_contact_to_list',
					[
						'email'   => 'user@example.com',
						'list_id' => '12',
						'status'  => 'subscribed',
					],
					$this->credentials
				),
				[]
			);

			$this->assertEquals( '101', $result['data']['activecampaign_contact_id'] );
			$this->assertEquals( '12', $result['data']['activecampaign_list_id'] );
			$this->assertEquals( '1', $result['data']['activecampaign_list_status'] );
		}

		public function test_add_tag_to_contact_assigns_tag(): void {
			$this->mockHttp(
				[
					'contact' => [
						'id'    => '101',
						'email' => 'user@example.com',
					],
				]
			);
			$this->mockHttp(
				[
					'contactTag' => [
						'id'      => '303',
						'contact' => '101',
						'tag'     => '55',
					],
				]
			);

			$result = ActiveCampaign::execute_node(
				$this->makeActionNode(
					'add_tag_to_contact',
					[
						'email'  => 'user@example.com',
						'tag_id' => '55',
					],
					$this->credentials
				),
				[]
			);

			$this->assertEquals( '303', $result['data']['activecampaign_contact_tag_id'] );
			$this->assertEquals( '55', $result['data']['activecampaign_tag_id'] );
		}

		public function test_remove_tag_from_contact_deletes_existing_relation(): void {
			$this->mockHttp(
				[
					'contact' => [
						'id'    => '101',
						'email' => 'user@example.com',
					],
				]
			);
			$this->mockHttp(
				[
					'contactTags' => [
						[
							'id'  => '303',
							'tag' => '55',
						],
					],
				]
			);
			$this->mockHttp( [] );

			$result = ActiveCampaign::execute_node(
				$this->makeActionNode(
					'remove_tag_from_contact',
					[
						'email'  => 'user@example.com',
						'tag_id' => '55',
					],
					$this->credentials
				),
				[]
			);

			$this->assertTrue( $result['data']['activecampaign_tag_removed'] );
			$this->assertEquals( '303', $result['data']['activecampaign_contact_tag_id'] );
		}

		public function test_add_contact_to_automation_adds_entry(): void {
			$this->mockHttp(
				[
					'contact' => [
						'id'    => '101',
						'email' => 'user@example.com',
					],
				]
			);
			$this->mockHttp(
				[
					'contactAutomation' => [
						'id'         => '808',
						'contact'    => '101',
						'automation' => '77',
					],
				]
			);

			$result = ActiveCampaign::execute_node(
				$this->makeActionNode(
					'add_contact_to_automation',
					[
						'email'         => 'user@example.com',
						'automation_id' => '77',
					],
					$this->credentials
				),
				[]
			);

			$this->assertEquals( '77', $result['data']['activecampaign_automation_id'] );
			$this->assertEquals( '808', $result['data']['activecampaign_contact_automation_id'] );
		}

		public function test_query_lists_returns_filtered_results(): void {
			$this->mockHttp(
				[
					'lists' => [
						[ 'id' => '12', 'name' => 'Newsletter' ],
						[ 'id' => '13', 'name' => 'Customers' ],
					],
				]
			);

			$result = ActiveCampaign::query_lists(
				[
					'search' => 'news',
				]
			);

			$this->assertCount( 1, $result );
			$this->assertEquals( '12', $result[0]['id'] );
		}

		public function test_test_connection_succeeds(): void {
			$this->mockHttp(
				[
					'user' => [
						'username' => 'zaplane-admin',
					],
				]
			);

			$result = ActiveCampaign::test_connection( $this->credentials );

			$this->assertTrue( $result['success'] );
			$this->assertStringContainsString( 'zaplane-admin', $result['message'] );
		}

		public function test_form_submitted_trigger_resolves_local_form_process_request(): void {
			$this->mockFormProcessRequest();

			$result = ActiveCampaign::resolve_trigger(
				$this->makeTriggerNode( 'form_submitted', [ 'form_id' => '44' ] ),
				[]
			);

			$this->assertIsArray( $result );
			$this->assertEquals( 'form_submitted', $result['event'] );
			$this->assertEquals( '44', $result['form_id'] );
			$this->assertEquals( 'user@example.com', $result['contact']['email'] );
		}

		public function test_contact_subscribed_trigger_filters_by_email_and_list(): void {
			$this->mockFormProcessRequest();

			$result = ActiveCampaign::resolve_trigger(
				$this->makeTriggerNode( 'contact_subscribed', [
					'email'   => 'user@example.com',
					'list_id' => '12',
				] ),
				[]
			);

			$this->assertIsArray( $result );
			$this->assertEquals( 'contact_subscribed', $result['event'] );
			$this->assertEquals( 'user@example.com', $result['contact']['email'] );
		}

		public function test_contact_unsubscribed_trigger_keeps_action_value(): void {
			$this->mockFormProcessRequest(
				[
					'act' => 'unsub',
				]
			);

			$result = ActiveCampaign::resolve_trigger(
				$this->makeTriggerNode( 'contact_unsubscribed' ),
				[]
			);

			$this->assertIsArray( $result );
			$this->assertEquals( 'contact_unsubscribed', $result['event'] );
			$this->assertEquals( 'unsub', $result['action'] );
		}

		public function test_resolve_trigger_returns_false_for_mismatched_filter(): void {
			$this->mockFormProcessRequest();

			$result = ActiveCampaign::resolve_trigger(
				$this->makeTriggerNode( 'contact_subscribed', [ 'email' => 'other@example.com' ] ),
				[]
			);

			$this->assertFalse( $result );
		}

		public function test_resolve_trigger_returns_false_without_payload(): void {
			$this->assertFalse( ActiveCampaign::resolve_trigger( $this->makeTriggerNode( 'missing' ), [] ) );
		}

		private function mockFormProcessRequest( array $post_overrides = [], array $get_overrides = [], array $server_overrides = [] ): void {
			$_POST = array_replace_recursive(
				[
					'f'         => '44',
					'act'       => 'sub',
					'email'     => 'user@example.com',
					'firstname' => 'John',
					'lastname'  => 'Doe',
					'phone'     => '+8801000000000',
					'field'     => [ '7' => 'VIP' ],
					'nlbox'     => [ '12' ],
				],
				$post_overrides
			);

			$_GET = array_replace(
				[
					'sync' => '1',
				],
				$get_overrides
			);

			$_SERVER = array_replace(
				[
					'REQUEST_METHOD' => 'POST',
					'REQUEST_URI'    => '/wp-content/plugins/activecampaign-subscription-forms/form_process.php?sync=1',
				],
				$server_overrides
			);
		}
	}
}
