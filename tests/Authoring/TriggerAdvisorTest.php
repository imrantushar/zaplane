<?php

namespace Zaplane\Tests\Authoring;

use Zaplane\Tests\TestCase;
use Zaplane\Authoring\TriggerAdvisor;

class TriggerAdvisorTest extends TestCase {

	private function trigger( string $id, string $app, string $event, array $data = [] ): array {
		return [
			'id'   => $id,
			'type' => 'trigger',
			'data' => array_merge(
				[
					'app'    => $app,
					'event'  => $event,
					'name'   => ucfirst( $app ),
					'config' => [],
				],
				$data
			),
		];
	}

	private function step( string $id, array $config = [] ): array {
		return [
			'id'   => $id,
			'type' => 'action',
			'data' => [
				'app'    => 'gemcrm',
				'event'  => 'send_email',
				'name'   => 'GemCRM',
				'config' => $config,
			],
		];
	}

	private function edge( string $source, string $target ): array {
		return [
			'id'     => 'e' . $source . '-' . $target,
			'source' => $source,
			'target' => $target,
		];
	}

	/** @return array<int,string> */
	private function codes( array $graph ): array {
		return array_column( TriggerAdvisor::warnings( $graph ), 'code' );
	}

	public function test_a_workflow_with_one_trigger_gets_no_warnings(): void {
		$graph = [
			'nodes' => [
				$this->trigger( '1', 'webhook', 'catch_hook' ),
				$this->step( '2', [ 'custom_email' => '{{1.email}}' ] ),
			],
			'edges' => [ $this->edge( '1', '2' ) ],
		];

		$this->assertSame( [], TriggerAdvisor::warnings( $graph ) );
	}

	public function test_it_flags_a_trigger_with_no_app_picked(): void {
		$graph = [
			'nodes' => [
				$this->trigger( '1', 'woocommerce', 'order_status_completed' ),
				[
					'id'   => '2',
					'type' => 'trigger',
					'data' => [
						'app'    => 'Select an app',
						'icon'   => 'plus',
						'config' => [],
					],
				],
				$this->step( '3' ),
			],
			'edges' => [ $this->edge( '1', '3' ), $this->edge( '2', '3' ) ],
		];

		$this->assertContains( 'trigger_empty', $this->codes( $graph ) );
	}

	public function test_it_flags_a_trigger_connected_to_nothing(): void {
		$graph = [
			'nodes' => [
				$this->trigger( '1', 'woocommerce', 'order_status_completed' ),
				$this->trigger( '2', 'woocommerce', 'order_status_processing' ),
				$this->step( '3' ),
			],
			'edges' => [ $this->edge( '1', '3' ) ],
		];

		$warnings = TriggerAdvisor::warnings( $graph );

		$this->assertSame( [ 'trigger_unconnected' ], array_column( $warnings, 'code' ) );
		$this->assertSame( '2', $warnings[0]['node_id'] );
		$this->assertStringContainsString( 'Trigger 2', $warnings[0]['message'] );
	}

	public function test_it_flags_two_identical_triggers(): void {
		$graph = [
			'nodes' => [
				$this->trigger( '1', 'woocommerce', 'order_status_completed', [ 'config' => [ 'a' => 1, 'b' => 2 ] ] ),
				// Same settings saved in a different order.
				$this->trigger( '2', 'woocommerce', 'order_status_completed', [ 'config' => [ 'b' => 2, 'a' => 1 ] ] ),
				$this->step( '3' ),
			],
			'edges' => [ $this->edge( '1', '3' ), $this->edge( '2', '3' ) ],
		];

		$this->assertContains( 'trigger_duplicate', $this->codes( $graph ) );
	}

	public function test_two_webhook_triggers_are_not_duplicates(): void {
		// Each Catch Webhook trigger has its own URL, so they never fire together.
		$graph = [
			'nodes' => [
				$this->trigger( '1', 'webhook', 'catch_hook', [ 'config' => [ 'secret' => 's1' ] ] ),
				$this->trigger( '2', 'webhook', 'catch_hook', [ 'config' => [ 'secret' => 's1' ] ] ),
				$this->step( '3' ),
			],
			'edges' => [ $this->edge( '1', '3' ), $this->edge( '2', '3' ) ],
		];

		$this->assertNotContains( 'trigger_duplicate', $this->codes( $graph ) );
	}

	public function test_it_flags_a_step_reading_fields_another_trigger_does_not_provide(): void {
		$graph = [
			'nodes' => [
				$this->trigger( '1', 'woocommerce', 'order_status_completed' ),
				$this->trigger( '2', 'fluentform', 'form_submitted' ),
				$this->step( '3', [ 'custom_email' => 'To: {{1.billing_email}}' ] ),
			],
			'edges' => [ $this->edge( '1', '3' ), $this->edge( '2', '3' ) ],
		];

		$gaps = array_values(
			array_filter( TriggerAdvisor::warnings( $graph ), fn( $warning ) => 'trigger_field_gap' === $warning['code'] )
		);

		$this->assertCount( 1, $gaps );
		$this->assertSame( '3', $gaps[0]['node_id'] );
	}

	public function test_matching_fields_to_the_trigger_the_step_reads_closes_the_gap(): void {
		$graph = [
			'nodes' => [
				$this->trigger( '1', 'woocommerce', 'order_status_completed' ),
				$this->trigger( '2', 'fluentform', 'form_submitted', [
					'field_map' => [
						'target' => '1',
						'fields' => [ 'billing_email' => '{{email}}' ],
					],
				] ),
				$this->step( '3', [ 'custom_email' => '{{1.billing_email}}' ] ),
			],
			'edges' => [ $this->edge( '1', '3' ), $this->edge( '2', '3' ) ],
		];

		$this->assertNotContains( 'trigger_field_gap', $this->codes( $graph ) );
	}

	public function test_a_field_match_that_leaves_out_a_field_the_step_reads_still_warns(): void {
		$graph = [
			'nodes' => [
				$this->trigger( '1', 'woocommerce', 'order_status_completed' ),
				$this->trigger( '2', 'fluentform', 'form_submitted', [
					'field_map' => [
						'target' => '1',
						'fields' => [ 'billing_email' => '{{email}}' ],
					],
				] ),
				$this->step( '3', [
					'custom_email' => '{{1.billing_email}}',
					'subject'      => 'Thanks {{1.first_name}}',
				] ),
			],
			'edges' => [ $this->edge( '1', '3' ), $this->edge( '2', '3' ) ],
		];

		$gaps = array_values(
			array_filter( TriggerAdvisor::warnings( $graph ), fn( $warning ) => 'trigger_field_gap' === $warning['code'] )
		);

		$this->assertCount( 1, $gaps );
		$this->assertStringContainsString( '{{1.first_name}}', $gaps[0]['message'] );
		$this->assertStringNotContainsString( '{{1.billing_email}}', $gaps[0]['message'] );
	}

	public function test_reading_whichever_trigger_fired_is_not_a_gap(): void {
		$graph = [
			'nodes' => [
				$this->trigger( '1', 'woocommerce', 'order_status_completed' ),
				$this->trigger( '2', 'fluentform', 'form_submitted' ),
				$this->step( '3', [ 'custom_email' => '{{trigger.email}}' ] ),
			],
			'edges' => [ $this->edge( '1', '3' ), $this->edge( '2', '3' ) ],
		];

		$this->assertNotContains( 'trigger_field_gap', $this->codes( $graph ) );
	}

	public function test_it_flags_an_unsecured_webhook_that_runs_the_same_steps_as_a_site_trigger(): void {
		$graph = [
			'nodes' => [
				$this->trigger( '1', 'woocommerce', 'order_status_completed' ),
				$this->trigger( '2', 'webhook', 'catch_hook' ),
				$this->step( '3' ),
			],
			'edges' => [ $this->edge( '1', '3' ), $this->edge( '2', '3' ) ],
		];

		$this->assertContains( 'webhook_without_secret', $this->codes( $graph ) );

		$graph['nodes'][1]['data']['config']['secret'] = 'long-random-string';

		$this->assertNotContains( 'webhook_without_secret', $this->codes( $graph ) );
	}

	public function test_it_flags_a_field_map_pointing_at_a_trigger_that_is_gone(): void {
		$graph = [
			'nodes' => [
				$this->trigger( '1', 'woocommerce', 'order_status_completed' ),
				$this->trigger( '2', 'fluentform', 'form_submitted', [
					'field_map' => [
						'target' => '9',
						'fields' => [ 'email' => '{{email}}' ],
					],
				] ),
				$this->step( '3' ),
			],
			'edges' => [ $this->edge( '1', '3' ), $this->edge( '2', '3' ) ],
		];

		$this->assertContains( 'field_map_target_missing', $this->codes( $graph ) );
	}
}
