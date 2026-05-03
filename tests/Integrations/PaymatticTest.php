<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Paymattic;

class PaymatticTest extends IntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'WPPAYFORM_VERSION' ) ) {
			define( 'WPPAYFORM_VERSION', 'test' );
		}
	}

	protected function getIntegrationClass(): string {
		return Paymattic::class;
	}

	protected function getTriggerTests(): array {
		return [
			'form_submitted' => [
				[ 'id' => 11, 'form_id' => 22 ],
				22,
			],
			'payment_success' => [
				[ 'id' => 5, 'form_id' => 9 ],
				[ 'id' => 88, 'submission_id' => 5, 'form_id' => 9 ],
				9,
				[ 'source' => 'test' ],
			],
			'payment_failed' => [
				[ 'id' => 7, 'form_id' => 10 ],
				10,
				[ 'id' => 77, 'form_id' => 10 ],
				'failed',
			],
		];
	}

	protected function getActionTests(): array {
		return [];
	}

	public function test_trigger_accepts_graph_node_data_shape(): void {
		$result = Paymattic::resolve_trigger(
			[
				'data' => [
					'event' => 'payment_success',
					'config' => [ 'form_id' => 'any' ],
				],
			],
			[
				[ 'id' => 5, 'form_id' => 9 ],
				[ 'id' => 88, 'submission_id' => 5, 'form_id' => 9 ],
				9,
				[ 'x' => 1 ],
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'payment_success', $result['event'] );
		$this->assertSame( 9, $result['form_id'] );
		$this->assertSame( 5, $result['submission_id'] );
	}

	public function test_execute_node_accepts_config_data_action_shape(): void {
		$result = Paymattic::execute_node(
			[
				'config' => [
					'action' => 'get_forms_all',
					'data' => [
						'limit' => 2,
						'search' => '',
					],
				],
			],
			[]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'main', $result['port'] );
		$this->assertArrayHasKey( 'items', $result['data'] );
		$this->assertArrayHasKey( 'total', $result['data'] );
	}

	public function test_payment_status_changed_respects_selected_status(): void {
		$result = Paymattic::resolve_trigger(
			$this->makeTriggerNode(
				'payment_status_changed',
				[
					'form_id' => 'any',
					'payment_status' => 'paid',
				]
			),
			[ 0, 'failed' ]
		);

		$this->assertFalse( $result );
	}

	public function test_query_forms_accepts_dynamic_payload_argument(): void {
		$result = Paymattic::query_forms(
			[
				'integration' => 'paymattic',
				'query' => 'forms',
			]
		);

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
		$this->assertSame( 'any', $result[0]['name'] ?? null );
	}
}
