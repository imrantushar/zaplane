<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\AbandonedCart;
use Zaplane\Modules\AbandonedCart\AbandonedCartModel;
use Zaplane\Tests\WPMocks;

class AbandonedCartTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return AbandonedCart::class;
	}

	protected function setUp(): void {
		parent::setUp();

		// Clear the static ORM query cache between tests.
		$reflector = new \ReflectionClass( \Zaplane\Framework\Database\ORM\QueryBuilder::class );
		$cacheProp = $reflector->getProperty( 'queryCache' );
		$cacheProp->setValue( null, [] );
	}

	protected function setupMockData(): void {
		WPMocks::reset();

		global $wpdb;
		$wpdb->reset();
		$wpdb->tables['results'] = [];
		$wpdb->tables['row']     = [];
	}

	// ── Base class wiring ──────────────────────────────────────────────────────

	protected function getTriggerTests(): array {
		$payload = $this->sampleCartArray();
		return [
			'cart_abandoned' => [ $payload ],
			'cart_recovered' => [ $payload ],
			'cart_lost'      => [ $payload ],
		];
	}

	protected function getActionTests(): array {
		return [
			// get_carts: empty DB → empty list on port main.
			'get_carts'          => [ 'status' => 'draft', 'limit' => 10 ],
			// update_cart_status: valid args → success on port main.
			'update_cart_status' => [ 'cart_id' => 1, 'status' => 'recovered' ],
			// get_report: empty DB (get_row returns []) → zero-counts report on port main.
			'get_report'         => [],
		];
	}

	// ── Helpers ────────────────────────────────────────────────────────────────

	private function sampleCartArray(): array {
		return [
			'id'           => 1,
			'full_name'    => 'John Doe',
			'email'        => 'john@example.com',
			'status'       => 'processing',
			'total'        => 99.99,
			'subtotal'     => 89.99,
			'shipping'     => 5.00,
			'tax'          => 5.00,
			'discounts'    => 0.00,
			'fees'         => 0.00,
			'currency'     => 'USD',
			'checkout_key' => 'abc123',
			'recovery_link'=> 'http://example.com/?zaplane=1',
			'contact_id'   => null,
			'order_id'     => null,
			'click_counts' => 0,
			'provider'     => 'woo',
			'user_id'      => 0,
			'abandoned_at' => '2026-01-01 10:00:00',
			'recovered_at' => null,
			'created_at'   => '2026-01-01 09:30:00',
		];
	}

	private function makeCartModel( array $fields = [] ): AbandonedCartModel {
		return AbandonedCartModel::hydrate( array_merge( [
			'id'           => 1,
			'full_name'    => 'John Doe',
			'email'        => 'john@example.com',
			'status'       => 'processing',
			'total'        => '99.99',
			'subtotal'     => '89.99',
			'shipping'     => '5.00',
			'tax'          => '5.00',
			'discounts'    => '0.00',
			'fees'         => '0.00',
			'currency'     => 'USD',
			'checkout_key' => 'abc123uuid',
			'cart_hash'    => 'hash456',
			'is_optout'    => 0,
			'user_id'      => 0,
			'contact_id'   => null,
			'order_id'     => null,
			'click_counts' => 0,
			'note'         => '',
			'provider'     => 'woo',
			'cart'         => json_encode( [] ),
			'abandoned_at' => '2026-01-01 10:00:00',
			'recovered_at' => null,
			'created_at'   => '2026-01-01 09:30:00',
			'updated_at'   => '2026-01-01 10:00:00',
		], $fields ) );
	}

	// ── resolve_trigger ────────────────────────────────────────────────────────

	public function test_resolve_trigger_returns_false_when_args_are_empty(): void {
		$node   = $this->makeTriggerNode( 'cart_abandoned' );
		$result = AbandonedCart::resolve_trigger( $node, [] );

		$this->assertFalse( $result );
	}

	public function test_resolve_trigger_returns_false_when_first_arg_is_null(): void {
		$node   = $this->makeTriggerNode( 'cart_abandoned' );
		$result = AbandonedCart::resolve_trigger( $node, [ null ] );

		$this->assertFalse( $result );
	}

	public function test_resolve_trigger_returns_array_when_passed_array_cart(): void {
		$node    = $this->makeTriggerNode( 'cart_abandoned' );
		$payload = $this->sampleCartArray();
		$result  = AbandonedCart::resolve_trigger( $node, [ $payload ] );

		$this->assertIsArray( $result );
		$this->assertSame( $payload, $result );
	}

	public function test_resolve_trigger_returns_cart_payload_when_passed_model(): void {
		$node  = $this->makeTriggerNode( 'cart_abandoned' );
		$model = $this->makeCartModel();
		$result = AbandonedCart::resolve_trigger( $node, [ $model ] );

		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['id'] );
		$this->assertSame( 'john@example.com', $result['email'] );
		$this->assertSame( 'John Doe', $result['full_name'] );
	}

	public function test_resolve_trigger_payload_contains_recovery_link(): void {
		$node  = $this->makeTriggerNode( 'cart_abandoned' );
		$model = $this->makeCartModel();
		$result = AbandonedCart::resolve_trigger( $node, [ $model ] );

		$this->assertArrayHasKey( 'recovery_link', $result );
		$this->assertStringContainsString( 'abc123uuid', $result['recovery_link'] );
	}

	public function test_resolve_trigger_cart_payload_contains_cart_contents(): void {
		$items = [ [ 'product_id' => 10, 'quantity' => 2 ] ];
		$model = $this->makeCartModel( [ 'cart' => json_encode( $items ) ] );
		$node  = $this->makeTriggerNode( 'cart_abandoned' );
		$result = AbandonedCart::resolve_trigger( $node, [ $model ] );

		$this->assertArrayHasKey( 'cart', $result );
		$this->assertSame( $items, $result['cart'] );
	}

	public function test_resolve_trigger_works_for_all_three_trigger_types(): void {
		$payload = $this->sampleCartArray();
		foreach ( [ 'cart_abandoned', 'cart_recovered', 'cart_lost' ] as $event ) {
			$node   = $this->makeTriggerNode( $event );
			$result = AbandonedCart::resolve_trigger( $node, [ $payload ] );

			$this->assertIsArray( $result, "Trigger '{$event}' should return array" );
		}
	}

	// ── get_trigger_sample_output ─────────────────────────────────────────────

	public function test_get_trigger_sample_output_returns_array_for_each_trigger(): void {
		foreach ( [ 'cart_abandoned', 'cart_recovered', 'cart_lost' ] as $event ) {
			$output = AbandonedCart::get_trigger_sample_output( $event );
			$this->assertIsArray( $output, "Sample output for '{$event}' must be array" );
		}
	}

	public function test_get_trigger_sample_output_contains_required_fields(): void {
		$output = AbandonedCart::get_trigger_sample_output( 'cart_abandoned' );

		$required = [ 'id', 'full_name', 'email', 'status', 'total', 'currency', 'recovery_link' ];
		foreach ( $required as $field ) {
			$this->assertArrayHasKey( $field, $output, "Sample output missing field: {$field}" );
		}
	}

	public function test_get_trigger_sample_output_cart_recovered_has_order_id(): void {
		$output = AbandonedCart::get_trigger_sample_output( 'cart_recovered' );

		$this->assertSame( 'recovered', $output['status'] );
		$this->assertNotNull( $output['order_id'] );
	}

	public function test_get_trigger_sample_output_cart_lost_has_lost_status(): void {
		$output = AbandonedCart::get_trigger_sample_output( 'cart_lost' );

		$this->assertSame( 'lost', $output['status'] );
	}

	// ── action: get_cart ──────────────────────────────────────────────────────

	public function test_get_cart_returns_error_when_cart_id_is_missing(): void {
		$node   = $this->makeActionNode( 'get_cart', [] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'error', $result['port'] );
		$this->assertStringContainsString( 'cart_id', $result['data']['error'] );
	}

	public function test_get_cart_returns_error_when_cart_not_found(): void {
		global $wpdb;
		$wpdb->tables['results'] = [];

		$node   = $this->makeActionNode( 'get_cart', [ 'cart_id' => 99 ] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'error', $result['port'] );
		$this->assertStringContainsString( 'not found', $result['data']['error'] );
	}

	public function test_get_cart_returns_cart_payload_when_found(): void {
		global $wpdb;
		$wpdb->tables['results'] = [ $this->makeCartModel()->toArray() ];

		$node   = $this->makeActionNode( 'get_cart', [ 'cart_id' => 1 ] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'main', $result['port'] );
		$this->assertSame( 1, $result['data']['id'] );
		$this->assertSame( 'john@example.com', $result['data']['email'] );
	}

	// ── action: get_cart_by_email ──────────────────────────────────────────────

	public function test_get_cart_by_email_returns_error_when_email_missing(): void {
		$node   = $this->makeActionNode( 'get_cart_by_email', [] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'error', $result['port'] );
		$this->assertStringContainsString( 'email', $result['data']['error'] );
	}

	public function test_get_cart_by_email_returns_error_when_not_found(): void {
		global $wpdb;
		$wpdb->tables['results'] = [];

		$node   = $this->makeActionNode( 'get_cart_by_email', [ 'email' => 'nobody@example.com' ] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'error', $result['port'] );
		$this->assertStringContainsString( 'No cart found', $result['data']['error'] );
	}

	public function test_get_cart_by_email_returns_cart_when_found(): void {
		global $wpdb;
		$wpdb->tables['results'] = [ $this->makeCartModel()->toArray() ];

		$node   = $this->makeActionNode( 'get_cart_by_email', [ 'email' => 'john@example.com' ] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'main', $result['port'] );
		$this->assertSame( 'john@example.com', $result['data']['email'] );
	}

	// ── action: get_carts ─────────────────────────────────────────────────────

	public function test_get_carts_returns_empty_list_when_no_carts(): void {
		$node   = $this->makeActionNode( 'get_carts', [] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'main', $result['port'] );
		$this->assertSame( [], $result['data']['carts'] );
		$this->assertSame( 0, $result['data']['count'] );
	}

	public function test_get_carts_returns_list_of_cart_payloads(): void {
		global $wpdb;
		$attrs = $this->makeCartModel()->toArray();
		$wpdb->tables['results'] = [ $attrs, array_merge( $attrs, [ 'id' => 2, 'email' => 'second@example.com' ] ) ];

		$node   = $this->makeActionNode( 'get_carts', [] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'main', $result['port'] );
		$this->assertCount( 2, $result['data']['carts'] );
		$this->assertSame( 2, $result['data']['count'] );
	}

	public function test_get_carts_clamps_limit_to_100(): void {
		// Just verify execution doesn't throw with an extreme limit.
		$node   = $this->makeActionNode( 'get_carts', [ 'limit' => 9999 ] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'main', $result['port'] );
	}

	// ── action: update_cart_status ────────────────────────────────────────────

	public function test_update_cart_status_returns_error_when_cart_id_missing(): void {
		$node   = $this->makeActionNode( 'update_cart_status', [ 'status' => 'recovered' ] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'error', $result['port'] );
	}

	public function test_update_cart_status_returns_error_when_status_missing(): void {
		$node   = $this->makeActionNode( 'update_cart_status', [ 'cart_id' => 1 ] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'error', $result['port'] );
	}

	public function test_update_cart_status_returns_error_for_invalid_status(): void {
		$node   = $this->makeActionNode( 'update_cart_status', [ 'cart_id' => 1, 'status' => 'deleted' ] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'error', $result['port'] );
		$this->assertStringContainsString( 'Invalid status', $result['data']['error'] );
	}

	public function test_update_cart_status_succeeds_for_all_valid_statuses(): void {
		$valid = [ 'draft', 'processing', 'recovered', 'lost', 'opt_out', 'skipped' ];

		foreach ( $valid as $status ) {
			$node   = $this->makeActionNode( 'update_cart_status', [ 'cart_id' => 1, 'status' => $status ] );
			$result = AbandonedCart::execute_node( $node, [] );

			$this->assertSame( 'main', $result['port'], "Status '{$status}' should succeed" );
			$this->assertTrue( $result['data']['updated'] );
			$this->assertSame( $status, $result['data']['status'] );
		}
	}

	public function test_update_cart_status_to_recovered_returns_updated_flag(): void {
		$node   = $this->makeActionNode( 'update_cart_status', [ 'cart_id' => 5, 'status' => 'recovered' ] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'main', $result['port'] );
		$this->assertSame( 5, $result['data']['cart_id'] );
		$this->assertTrue( $result['data']['updated'] );
	}

	// ── action: get_report ────────────────────────────────────────────────────

	public function test_get_report_returns_summary_structure(): void {
		$node   = $this->makeActionNode( 'get_report', [] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'main', $result['port'] );
		$this->assertArrayHasKey( 'summary', $result['data'] );
		$this->assertArrayHasKey( 'recovery_rate', $result['data'] );
		$this->assertArrayHasKey( 'total_carts', $result['data'] );
		$this->assertArrayHasKey( 'date_range', $result['data'] );
	}

	public function test_get_report_summary_contains_all_statuses(): void {
		$node    = $this->makeActionNode( 'get_report', [] );
		$result  = AbandonedCart::execute_node( $node, [] );
		$summary = $result['data']['summary'];

		foreach ( [ 'recovered', 'processing', 'lost', 'draft', 'opt_out' ] as $status ) {
			$this->assertArrayHasKey( $status, $summary, "Summary missing status: {$status}" );
			$this->assertArrayHasKey( 'count', $summary[ $status ] );
			$this->assertArrayHasKey( 'revenue', $summary[ $status ] );
		}
	}

	public function test_get_report_returns_zero_recovery_rate_when_no_carts(): void {
		$node   = $this->makeActionNode( 'get_report', [] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 0, $result['data']['total_carts'] );
		$this->assertSame( 0.0, (float) $result['data']['recovery_rate'] );
	}

	public function test_get_report_respects_date_range_params(): void {
		$node   = $this->makeActionNode( 'get_report', [ 'date_from' => '2026-01-01', 'date_to' => '2026-01-31' ] );
		$result = AbandonedCart::execute_node( $node, [] );

		$this->assertSame( 'main', $result['port'] );
		$this->assertSame( '2026-01-01', $result['data']['date_range']['from'] );
		$this->assertSame( '2026-01-31', $result['data']['date_range']['to'] );
	}

	// ── Integration registration ───────────────────────────────────────────────

	public function test_slug_is_abandoned_cart(): void {
		$this->assertSame( 'abandoned-cart', AbandonedCart::get_slug() );
	}

	public function test_has_exactly_three_triggers(): void {
		$this->assertCount( 3, AbandonedCart::get_triggers() );
	}

	public function test_has_exactly_five_actions(): void {
		$this->assertCount( 5, AbandonedCart::get_actions() );
	}

	public function test_triggers_have_expected_keys(): void {
		$triggers = AbandonedCart::get_triggers();

		$this->assertArrayHasKey( 'cart_abandoned', $triggers );
		$this->assertArrayHasKey( 'cart_recovered', $triggers );
		$this->assertArrayHasKey( 'cart_lost', $triggers );
	}

	public function test_actions_have_expected_keys(): void {
		$actions = AbandonedCart::get_actions();

		foreach ( [ 'get_cart', 'get_cart_by_email', 'get_carts', 'update_cart_status', 'get_report' ] as $key ) {
			$this->assertArrayHasKey( $key, $actions, "Action '{$key}' not registered" );
		}
	}

	public function test_all_triggers_fire_on_correct_hooks(): void {
		$expected_hooks = [
			'cart_abandoned' => 'zaplane/abandoned_cart/started',
			'cart_recovered' => 'zaplane/abandoned_cart/recovered',
			'cart_lost'      => 'zaplane/abandoned_cart/lost',
		];

		foreach ( AbandonedCart::get_triggers() as $event => $meta ) {
			$this->assertSame(
				$expected_hooks[ $event ],
				$meta['hook'],
				"Trigger '{$event}' has wrong hook"
			);
		}
	}
}
