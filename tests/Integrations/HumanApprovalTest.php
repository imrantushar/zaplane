<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\HumanApproval;
use Zaplane\Tests\TestCase;

class HumanApprovalTest extends TestCase {

	private function base_node(): array {
		return [
			'id'           => 7,
			'_run_id'      => 123,
			'_node_run_id' => 456,
			'data'         => [
				'config' => [
					'approver_emails' => 'boss@example.com',
					'subject'         => 'Approve order #999',
					'message'         => 'Please approve order #999',
				],
			],
		];
	}

	/**
	 * A real run (run context present) must PAUSE, not send downstream. The engine
	 * treats status "delayed" as a barrier and never spawns children for it.
	 */
	public function test_real_run_halts_and_waits_for_a_decision() {
		$result = HumanApproval::execute_node( $this->base_node(), [ 'order_id' => 999 ] );

		$this->assertEquals( '__halt__', $result['port'] );
		$this->assertEquals( 'delayed', $result['status'] );
	}

	/**
	 * The footgun the user hit: a test invocation has no run context, so the node
	 * cannot pause. It passes through as "approved" and sends NO approval email —
	 * which is why the only email that arrives is the downstream Send Email.
	 */
	public function test_test_mode_passes_through_without_pausing() {
		$node = $this->base_node();
		unset( $node['_run_id'], $node['_node_run_id'] );

		$result = HumanApproval::execute_node( $node, [ 'order_id' => 999 ] );

		$this->assertEquals( 'approved', $result['port'] );
		$this->assertEquals( 'approved', $result['data']['approval_status'] );
		$this->assertArrayNotHasKey( 'status', $result, 'Pass-through must not signal a halt.' );
	}

	/**
	 * The approval email carries both signed decision buttons when reject is allowed.
	 */
	public function test_email_contains_both_approve_and_reject_buttons() {
		$html = $this->render_email( true );

		$this->assertStringContainsString( 'decision=approved', $html );
		$this->assertStringContainsString( 'decision=rejected', $html );
		$this->assertStringContainsString( '>Approve<', $html );
		$this->assertStringContainsString( '>Reject<', $html );
	}

	/**
	 * With reject disabled, only the approve button is rendered.
	 */
	public function test_reject_button_hidden_when_not_allowed() {
		$html = $this->render_email( false );

		$this->assertStringContainsString( 'decision=approved', $html );
		$this->assertStringNotContainsString( 'decision=rejected', $html );
		$this->assertStringNotContainsString( '>Reject<', $html );
	}

	/**
	 * The signed link is the only auth on the public respond endpoint — a tampered
	 * decision must not verify against the original signature.
	 */
	public function test_signature_round_trips_and_rejects_tampering() {
		$args = [ 'run' => 123, 'nr' => 456, 'nk' => 7, 'exp' => 1783671543, 'decision' => 'approved' ];
		$sig  = HumanApproval::sign( $args );

		$this->assertEquals( $sig, HumanApproval::sign( $args ), 'Signature must be deterministic.' );

		$tampered = array_merge( $args, [ 'decision' => 'rejected' ] );
		$this->assertNotEquals( $sig, HumanApproval::sign( $tampered ), 'Flipping the decision must break the signature.' );
	}

	private function render_email( bool $allow_reject ): string {
		$expires  = 1783671543;
		$approve  = HumanApproval::response_url( 123, 456, 7, 'approved', $expires );
		$reject   = HumanApproval::response_url( 123, 456, 7, 'rejected', $expires );
		$method   = new \ReflectionMethod( HumanApproval::class, 'email_html' );
		$method->setAccessible( true );

		return $method->invoke( null, 'Please approve', $approve, 'Approve', $reject, 'Reject', $allow_reject );
	}
}
