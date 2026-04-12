<?php

namespace {
	require_once dirname( __DIR__ ) . '/mocks/MemberpressTestStubs.php';
}

namespace Zaplane\Tests\Integrations {

use Zaplane\Integrations\Memberpress;

class MemberpressTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Memberpress::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();

		\MeprUser::reset_store();
		\MeprProduct::reset_store();
		\MeprTransaction::reset_store();
		\MeprSubscription::reset_store();

		\MeprUser::seed( 1, [] );
		\MeprProduct::seed( 12, [] );
		\MeprTransaction::seed( 41, [] );
		\MeprSubscription::seed( 31, [] );
	}

	protected function getTriggerTests(): array {
		return [
			'member_added'                         => [ $this->makeEvent( 'member_added', 'users', $this->makeUser( 1 ) ) ],
			'member_signup_completed'              => [ $this->makeEvent( 'member_signup_completed', 'users', $this->makeUser( 1 ) ) ],
			'member_account_updated'               => [ $this->makeEvent( 'member_account_updated', 'users', $this->makeUser( 1 ) ) ],
			'member_deleted'                       => [ $this->makeEvent( 'member_deleted', 'users', $this->makeUser( 1 ) ) ],
			'login'                                => [ $this->makeEvent( 'login', 'users', $this->makeUser( 1 ) ) ],
			'subscription_created'                 => [ $this->makeEvent( 'subscription_created', 'subscriptions', $this->makeSubscription( 31 ) ) ],
			'subscription_paused'                  => [ $this->makeEvent( 'subscription_paused', 'subscriptions', $this->makeSubscription( 31 ) ) ],
			'subscription_resumed'                 => [ $this->makeEvent( 'subscription_resumed', 'subscriptions', $this->makeSubscription( 31 ) ) ],
			'subscription_stopped'                 => [ $this->makeEvent( 'subscription_stopped', 'subscriptions', $this->makeSubscription( 31 ) ) ],
			'subscription_upgraded'                => [ $this->makeEvent( 'subscription_upgraded', 'subscriptions', $this->makeSubscription( 31 ) ) ],
			'subscription_downgraded'              => [ $this->makeEvent( 'subscription_downgraded', 'subscriptions', $this->makeSubscription( 31 ) ) ],
			'subscription_upgraded_to_one_time'    => [ $this->makeEvent( 'subscription_upgraded_to_one_time', 'subscriptions', $this->makeSubscription( 31 ) ) ],
			'subscription_upgraded_to_recurring'   => [ $this->makeEvent( 'subscription_upgraded_to_recurring', 'subscriptions', $this->makeSubscription( 31 ) ) ],
			'subscription_downgraded_to_one_time'  => [ $this->makeEvent( 'subscription_downgraded_to_one_time', 'subscriptions', $this->makeSubscription( 31 ) ) ],
			'subscription_downgraded_to_recurring' => [ $this->makeEvent( 'subscription_downgraded_to_recurring', 'subscriptions', $this->makeSubscription( 31 ) ) ],
			'subscription_expired'                 => [ $this->makeEvent( 'subscription_expired', 'subscriptions', $this->makeSubscription( 31 ) ) ],
			'subscription_changed'                 => [ $this->makeEvent( 'subscription_changed', 'subscriptions', $this->makeSubscription( 31 ) ) ],
			'transaction_completed'                => [ $this->makeEvent( 'transaction_completed', 'transactions', $this->makeTransaction( 41 ) ) ],
			'transaction_refunded'                 => [ $this->makeEvent( 'transaction_refunded', 'transactions', $this->makeTransaction( 41 ) ) ],
			'transaction_failed'                   => [ $this->makeEvent( 'transaction_failed', 'transactions', $this->makeTransaction( 41 ) ) ],
			'transaction_expired'                  => [ $this->makeEvent( 'transaction_expired', 'transactions', $this->makeTransaction( 41 ) ) ],
			'offline_payment_pending'              => [ $this->makeEvent( 'offline_payment_pending', 'transactions', $this->makeTransaction( 41 ) ) ],
			'offline_payment_complete'             => [ $this->makeEvent( 'offline_payment_complete', 'transactions', $this->makeTransaction( 41 ) ) ],
			'offline_payment_refunded'             => [ $this->makeEvent( 'offline_payment_refunded', 'transactions', $this->makeTransaction( 41 ) ) ],
			'recurring_transaction_completed'      => [ $this->makeEvent( 'recurring_transaction_completed', 'transactions', $this->makeTransaction( 41 ) ) ],
			'renewal_transaction_completed'        => [ $this->makeEvent( 'renewal_transaction_completed', 'transactions', $this->makeTransaction( 41 ) ) ],
			'recurring_transaction_failed'         => [ $this->makeEvent( 'recurring_transaction_failed', 'transactions', $this->makeTransaction( 41 ) ) ],
			'recurring_transaction_expired'        => [ $this->makeEvent( 'recurring_transaction_expired', 'transactions', $this->makeTransaction( 41 ) ) ],
			'recurring_transaction_refunded'       => [ $this->makeEvent( 'recurring_transaction_refunded', 'transactions', $this->makeTransaction( 41 ) ) ],
			'non_recurring_transaction_completed'  => [ $this->makeEvent( 'non_recurring_transaction_completed', 'transactions', $this->makeTransaction( 41 ) ) ],
			'non_recurring_transaction_expired'    => [ $this->makeEvent( 'non_recurring_transaction_expired', 'transactions', $this->makeTransaction( 41 ) ) ],
			'account_is_active'                    => [ $this->makeEvent( 'account_is_active', 'users', $this->makeUser( 1 ) ) ],
			'account_is_inactive'                  => [ $this->makeEvent( 'account_is_inactive', 'users', $this->makeUser( 1 ) ) ],
		];
	}

	protected function getActionTests(): array {
		return [
			'create_member'              => [ 'email' => 'member@example.com', 'username' => 'memberuser', 'first_name' => 'Member', 'last_name' => 'Test' ],
			'create_membership'          => [ 'name' => 'Gold Plan', 'price' => '49.99', 'period' => '1', 'period_type' => 'months' ],
			'update_membership'          => [ 'membership_id' => 12, 'name' => 'Updated Plan', 'price' => '59.99' ],
			'create_transaction'         => [ 'user_id' => 1, 'product_id' => 12, 'amount' => '19.99', 'subscription_id' => 31 ],
			'update_transaction_status'  => [ 'transaction_id' => 41, 'transaction_status' => 'mp_transaction_refunded' ],
			'refund_transaction'         => [ 'transaction_id' => 41 ],
			'create_subscription'        => [ 'user_id' => 1, 'product_id' => 12, 'price' => '29.99', 'period' => '1', 'period_type' => 'months' ],
			'update_subscription_status' => [ 'subscription_id' => 31, 'subscription_status' => 'mp_subscription_suspended' ],
			'cancel_subscription'        => [ 'subscription_id' => 31 ],
			'suspend_subscription'       => [ 'subscription_id' => 31 ],
			'resume_subscription'        => [ 'subscription_id' => 31 ],
		];
	}

	private function makeUser( int $id ): \MeprUser {
		return new \MeprUser( $id );
	}

	private function makeTransaction( int $id ): \MeprTransaction {
		return new \MeprTransaction( $id );
	}

	private function makeSubscription( int $id ): \MeprSubscription {
		return new \MeprSubscription( $id );
	}

	private function makeEvent( string $event_name, string $id_type, object $data, ?int $evt_id = null, $args = null ): object {
		$evt_id = null === $evt_id
			? (int) ( $data->ID ?? ( $data->id ?? 1 ) )
			: $evt_id;
		$args = null === $args ? wp_json_encode( [ 'source' => 'test' ] ) : $args;

		return new class( $event_name, $id_type, $data, $evt_id, $args ) {
			public string $event;
			public int $evt_id;
			public string $evt_id_type;
			public string $created_at;
			public $args;
			private object $data;

			public function __construct( string $event, string $evt_id_type, object $data, int $evt_id, $args ) {
				$this->event = $event;
				$this->evt_id = $evt_id;
				$this->evt_id_type = $evt_id_type;
				$this->created_at = '2024-01-01 00:00:00';
				$this->args = $args;
				$this->data = $data;
			}

			public function get_data(): object {
				return $this->data;
			}
		};
	}

	public function test_trigger_member_added_returns_user_payload_and_decodes_args(): void {
		$result = Memberpress::resolve_trigger(
			$this->makeTriggerNode( 'member_added' ),
			[ $this->makeEvent( 'member_added', 'users', $this->makeUser( 1 ), 1, wp_json_encode( [ 'source' => 'signup' ] ) ) ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['user_id'] );
		$this->assertEquals( 'member1@example.com', $result['user']['email'] );
		$this->assertEquals( 'signup', $result['args']['source'] );
	}

	public function test_trigger_returns_false_for_invalid_event_object(): void {
		$result = Memberpress::resolve_trigger(
			$this->makeTriggerNode( 'member_added' ),
			[ (object) [ 'event' => '', 'evt_id' => 0 ] ]
		);

		$this->assertFalse( $result );
	}

	public function test_action_create_membership_returns_membership_payload(): void {
		$result = Memberpress::execute_node(
			$this->makeActionNode( 'create_membership', [ 'name' => 'Gold Plan', 'price' => '49.99' ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertArrayHasKey( 'membership_id', $result['data'] );
	}

	public function test_action_refund_transaction_marks_transaction_refunded(): void {
		$result = Memberpress::execute_node(
			$this->makeActionNode( 'refund_transaction', [ 'transaction_id' => 41 ] ),
			[]
		);

		$this->assertTrue( $result['data']['refunded'] );
	}

	public function test_action_update_subscription_status_returns_error_without_status(): void {
		$result = Memberpress::execute_node(
			$this->makeActionNode( 'update_subscription_status', [ 'subscription_id' => 31 ] ),
			[]
		);

		$this->assertEquals( 'Subscription ID and status are required', $result['data']['error'] );
	}
}
}
