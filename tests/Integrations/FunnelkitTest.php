<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Funnelkit;
use Zaplane\Tests\WPMocks;

class FunnelkitTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Funnelkit::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();

		\FunnelkitTestStore::reset();

		WPMocks::setPost(
			901,
			[
				'post_type'     => 'wffn_landing',
				'post_title'    => 'Landing Step',
				'post_status'   => 'publish',
				'post_date'     => '2026-04-01 10:05:00',
				'post_modified' => '2026-04-01 10:06:00',
			]
		);

		WPMocks::setPost(
			902,
			[
				'post_type'     => 'wfacp_checkout',
				'post_title'    => 'Checkout Step',
				'post_status'   => 'publish',
				'post_date'     => '2026-04-01 10:07:00',
				'post_modified' => '2026-04-01 10:08:00',
			]
		);

		WPMocks::setPost(
			903,
			[
				'post_type'     => 'wffn_ty',
				'post_title'    => 'Thank You Step',
				'post_status'   => 'publish',
				'post_date'     => '2026-04-01 10:09:00',
				'post_modified' => '2026-04-01 10:10:00',
			]
		);
	}

	protected function getTriggerTests(): array {
		return [
			'woofunnels_loaded'       => [ '/tmp/woofunnels/' ],
			'core_modules_loaded'     => [],
			'loaded'                  => [],
			'funnel_created'          => [ 801, [ [ 'id' => 901, 'type' => 'landing' ], [ 'id' => 902, 'type' => 'wc_checkout' ] ] ],
			'duplicate_funnel'        => [ [ 'id' => 802, 'title' => 'Main Funnel Copy' ], [ 'id' => 801, 'title' => 'Main Funnel' ] ],
			'funnel_imported'         => [ 801, [ 'title' => 'Imported Funnel' ], [ [ 'id' => 801 ] ] ],
			'funnel_updated'          => [ 801, [ 'title' => 'Updated Funnel' ] ],
			'step_duplicated'         => [ 902 ],
			'step_viewed'             => [ 901, [ 'url' => 'https://example.com' ] ],
			'step_converted'          => [ 902, [ 'source' => 'test' ] ],
			'funnel_ended'            => [ [ 'id' => 903, 'type' => 'wc_thankyou' ], new \WFFN_Funnel( 801 ) ],
			'ty_funnel_ended'         => [ new \WFFN_Funnel( 801 ), 9001 ],
			'import_completed'        => [ 801, [ 'id' => 902 ], 'gutenberg', 'starter-template' ],
			'importing_completed'     => [],
			'template_import_remote'  => [ 801, 'gutenberg', 'starter-template', [ 'id' => 902 ] ],
			'container'               => [],
			'container_top'           => [],
			'container_bottom'        => [],
			'wp_footer'               => [],
			'checkout_loaded'         => [],
			'template_body_top'       => [],
			'template_container_top'  => [],
			'template_container_bottom'=> [],
			'template_wp_footer'      => [],
		];
	}

	protected function getActionTests(): array {
		return [
			'get_funnel_single' => [ 'funnel_id' => 801 ],
			'get_step_single'   => [ 'step_id' => 901 ],
			'get_next_step'     => [ 'step_id' => 901 ],
			'add_action'        => [ 'hook_name' => 'funnelkit/custom_hook', 'accepted_args' => 2 ],
			'do_action'         => [ 'hook_name' => 'funnelkit/custom_hook', 'arg_1' => 'one', 'arg_2' => 'two' ],
			'add_filter'        => [ 'hook_name' => 'funnelkit/custom_filter', 'accepted_args' => 1, 'return_value' => 'patched' ],
			'apply_filters'     => [ 'hook_name' => 'funnelkit/custom_filter', 'value' => 'original', 'arg_1' => 'one', 'arg_2' => 'two' ],
			'remove_action'     => [ 'hook_name' => 'funnelkit/custom_hook' ],
			'has_action'        => [ 'hook_name' => 'funnelkit/custom_hook' ],
			'current_filter'    => [],
		];
	}

	public function test_integration_exposes_introduction(): void {
		$this->assertSame(
			'Track FunnelKit funnel and step lifecycle events, and run hook-based automation actions without webhooks.',
			Funnelkit::get_introduction()
		);
	}

	public function test_trigger_step_viewed_returns_step_and_funnel_payload(): void {
		$result = Funnelkit::resolve_trigger(
			$this->makeTriggerNode( 'step_viewed' ),
			[ 901, [ 'url' => 'https://example.com' ] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 901, $result['step_id'] );
		$this->assertEquals( 801, $result['funnel_id'] );
	}

	public function test_action_get_next_step_returns_current_and_next_step_payload(): void {
		$result = Funnelkit::execute_node(
			$this->makeActionNode(
				'get_next_step',
				[
					'step_id' => 901,
				]
			),
			[]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'main', $result['port'] );
		$this->assertEquals( 901, $result['data']['current_step']['step_id'] );
		$this->assertEquals( 902, $result['data']['next_step']['step_id'] );
	}

	public function test_action_get_funnel_single_resolves_funnel_id_from_input_payload(): void {
		$result = Funnelkit::execute_node(
			$this->makeActionNode( 'get_funnel_single', [] ),
			[
				'event'     => 'step_viewed',
				'funnel_id' => 801,
				'step_id'   => 901,
			]
		);

		$this->assertSame( 'main', $result['port'] );
		$this->assertEquals( 801, $result['data']['funnel']['funnel_id'] );
	}

	public function test_dynamic_queries_include_seeded_funnel_and_steps(): void {
		$queries = Funnelkit::get_dynamic_queries();

		$this->assertArrayHasKey( 'funnels', $queries );
		$this->assertArrayHasKey( 'steps', $queries );

		$funnels = Funnelkit::query_funnels( [] );
		$steps   = Funnelkit::query_steps( [] );

		$this->assertContains(
			[
				'name'  => '801',
				'label' => 'Main Funnel',
			],
			$funnels
		);

		$this->assertContains(
			[
				'name'  => '902',
				'label' => 'Checkout Step (wc_checkout)',
			],
			$steps
		);
	}
}

