<?php

namespace Zaplane\Tests\Modules;

use Zaplane\Tests\TestCase;
use Zaplane\Tests\WPMocks;
use Zaplane\Framework\Core\Automation;
use Zaplane\Framework\Classes\Container;

class AutomationRunWorkflowTest extends TestCase {

	private Automation $automation;

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb->reset();
		WPMocks::reset();

		// Clear static ORM query cache between tests.
		$ref = new \ReflectionClass( \Zaplane\Framework\Database\ORM\QueryBuilder::class );
		$ref->getProperty( 'queryCache' )->setValue( null, [] );

		// Clear static Schema prefix cache.
		\Zaplane\Framework\Database\ORM\Schema::resetPrefix();

		$this->automation = new Automation( new Container() );

		// Wire up the singleton so \zaplane_run_workflow() can find it.
		( new \ReflectionClass( Automation::class ) )
			->getProperty( 'instance' )
			->setValue( null, $this->automation );
	}

	protected function tearDown(): void {
		( new \ReflectionClass( Automation::class ) )
			->getProperty( 'instance' )
			->setValue( null, null );
		parent::tearDown();
	}

	// ── Helpers ────────────────────────────────────────────────────────────────

	private function triggerNode( array $overrides = [] ): array {
		return array_merge( [
			'id'   => '1',
			'type' => 'trigger',
			'data' => [
				'app'   => 'abandoned-cart',
				'event' => 'cart_abandoned',
				'hook'  => 'zaplane/abandoned_cart/started',
				'label' => 'WooCommerce Abandoned Cart',
			],
		], $overrides );
	}

	private function graphJson( array $nodes = [], array $edges = [] ): string {
		return wp_json_encode( [ 'nodes' => $nodes, 'edges' => $edges ] );
	}

	/**
	 * Preload results_sequence for the two SELECT queries run_workflow issues:
	 *   [0] Workflow::find()        — expects [workflowRow] or []
	 *   [1] activeVersion() first() — expects [versionRow]  or []
	 */
	private function setupSequence( ?array $workflowRow = null, ?array $versionRow = null ): void {
		global $wpdb;

		$workflow = $workflowRow !== null
			? [ array_merge( [ 'id' => 1, 'user_id' => 1, 'status' => 'active', 'title' => 'Abandoned Cart', 'name' => 'abandoned-cart', 'folder_id' => 0 ], $workflowRow ) ]
			: [];

		$version = $versionRow !== null
			? [ array_merge( [ 'id' => 5, 'workflow_id' => 1, 'is_active' => 1, 'version_number' => 1, 'graph_hash' => 'abc' ], $versionRow ) ]
			: [];

		$wpdb->tables['results_sequence'] = [ $workflow, $version ];
		$wpdb->tables['next_insert_id']   = 100;
	}

	// ── run_workflow: failure paths ────────────────────────────────────────────

	public function test_returns_false_when_workflow_not_found(): void {
		$this->setupSequence( null );

		$this->assertFalse( $this->automation->run_workflow( 99 ) );
	}

	public function test_returns_false_when_no_active_version(): void {
		$this->setupSequence( [], null );

		$this->assertFalse( $this->automation->run_workflow( 1 ) );
	}

	public function test_returns_false_when_graph_has_no_trigger_node(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [
				[ 'id' => '2', 'type' => 'action', 'data' => [ 'app' => 'gemcrm', 'event' => 'send_email' ] ],
			] ) ]
		);

		$this->assertFalse( $this->automation->run_workflow( 1 ) );
	}

	// ── run_workflow: happy path ───────────────────────────────────────────────

	public function test_returns_integer_run_id_on_success(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [ $this->triggerNode() ] ) ]
		);

		$result = $this->automation->run_workflow( 1, [ 'email' => 'guest@example.com' ] );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	public function test_returns_the_inserted_run_id(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [ $this->triggerNode() ] ) ]
		);

		$this->assertSame( 100, $this->automation->run_workflow( 1 ) );
	}

	public function test_works_with_empty_data_array(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [ $this->triggerNode() ] ) ]
		);

		$this->assertSame( 100, $this->automation->run_workflow( 1 ) );
	}

	// ── run_workflow: Run record ───────────────────────────────────────────────

	public function test_inserts_a_run_record(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [ $this->triggerNode() ] ) ]
		);

		$this->automation->run_workflow( 1, [] );

		global $wpdb;
		$this->assertNotEmpty(
			$wpdb->tables['wp_zaplane_runs'] ?? [],
			'Expected a row in wp_zaplane_runs after run_workflow'
		);
	}

	public function test_run_record_contains_custom_trigger_data(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [ $this->triggerNode() ] ) ]
		);

		$this->automation->run_workflow( 1, [
			'email'      => 'cart@example.com',
			'cart_total' => 149.99,
		] );

		global $wpdb;
		$row         = $wpdb->tables['wp_zaplane_runs'][0];
		$triggerData = json_decode( $row['trigger_data'], true );

		$this->assertSame( 'cart@example.com', $triggerData['email'] );
		$this->assertSame( 149.99, $triggerData['cart_total'] );
	}

	public function test_run_record_has_status_running(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [ $this->triggerNode() ] ) ]
		);

		$this->automation->run_workflow( 1, [] );

		global $wpdb;
		$row = $wpdb->tables['wp_zaplane_runs'][0];
		$this->assertSame( 'running', $row['status'] );
	}

	public function test_run_record_has_correct_workflow_id(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [ $this->triggerNode() ] ) ]
		);

		$this->automation->run_workflow( 1, [] );

		global $wpdb;
		$row = $wpdb->tables['wp_zaplane_runs'][0];
		$this->assertSame( 1, $row['workflow_id'] );
	}

	public function test_run_record_start_node_key_matches_trigger_node_id(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [ $this->triggerNode() ] ) ]
		);

		$this->automation->run_workflow( 1, [] );

		global $wpdb;
		$row = $wpdb->tables['wp_zaplane_runs'][0];
		// Trigger node id is '1'; start_node_key should be integer 1.
		$this->assertSame( 1, $row['start_node_key'] );
	}

	// ── run_workflow: NodeRun enqueue ─────────────────────────────────────────

	public function test_enqueues_exactly_one_node_run(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [ $this->triggerNode() ] ) ]
		);

		$this->automation->run_workflow( 1, [] );

		$this->assertCount( 1, WPMocks::getEnqueuedNodeRuns() );
	}

	public function test_enqueued_node_run_id_matches_inserted_node_run(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [ $this->triggerNode() ] ) ]
		);

		$this->automation->run_workflow( 1, [] );

		$enqueued = WPMocks::getEnqueuedNodeRuns();
		$this->assertSame( 100, $enqueued[0]['node_run_id'] );
	}

	// ── run_workflow: full abandoned-cart graph ───────────────────────────────

	// ── run_workflow: choosing the trigger ────────────────────────────────────

	public function test_starts_from_the_named_trigger(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [ $this->triggerNode(), $this->triggerNode( [ 'id' => '2' ] ) ] ) ]
		);

		$this->automation->run_workflow( 1, [], '2' );

		global $wpdb;
		$this->assertSame( 2, $wpdb->tables['wp_zaplane_runs'][0]['start_node_key'] );
	}

	public function test_defaults_to_the_manual_trigger_when_the_workflow_has_one(): void {
		$manual = [
			'id'   => '2',
			'type' => 'trigger',
			'data' => [ 'app' => 'manual', 'event' => 'run_manually', 'label' => 'Manual' ],
		];

		$this->setupSequence( [], [ 'graph_json' => $this->graphJson( [ $this->triggerNode(), $manual ] ) ] );

		$this->automation->run_workflow( 1, [] );

		global $wpdb;
		$this->assertSame( 2, $wpdb->tables['wp_zaplane_runs'][0]['start_node_key'] );
	}

	public function test_defaults_to_the_first_trigger_without_a_manual_one(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [ $this->triggerNode(), $this->triggerNode( [ 'id' => '2' ] ) ] ) ]
		);

		$this->automation->run_workflow( 1, [] );

		global $wpdb;
		$this->assertSame( 1, $wpdb->tables['wp_zaplane_runs'][0]['start_node_key'] );
	}

	public function test_returns_false_when_the_named_node_is_not_a_trigger(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [
				$this->triggerNode(),
				[ 'id' => '2', 'type' => 'action', 'data' => [ 'app' => 'gemcrm', 'event' => 'send_email' ] ],
			] ) ]
		);

		$this->assertFalse( $this->automation->run_workflow( 1, [], '2' ) );
	}

	public function test_works_with_full_abandoned_cart_workflow_graph(): void {
		$nodes = [
			$this->triggerNode(),
			[ 'id' => '2', 'type' => 'action', 'data' => [ 'app' => 'gemcrm', 'event' => 'send_email', 'label' => 'Send Email' ] ],
			[ 'id' => '3', 'type' => 'delay',  'data' => [ 'app' => 'delay',  'label' => 'Wait 3 days' ] ],
			[ 'id' => '4', 'type' => 'action', 'data' => [ 'app' => 'gemcrm', 'event' => 'send_email', 'label' => 'Follow-up Email' ] ],
		];
		$edges = [
			[ 'source' => '1', 'target' => '2' ],
			[ 'source' => '2', 'target' => '3' ],
			[ 'source' => '3', 'target' => '4' ],
		];

		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( $nodes, $edges ) ]
		);

		$result = $this->automation->run_workflow( 1, [
			'email'      => 'abandoned@example.com',
			'cart_total' => 149.99,
			'cart_id'    => 7,
		] );

		$this->assertSame( 100, $result );
	}

	// ── \zaplane_run_workflow() global helper ──────────────────────────────────

	public function test_global_helper_returns_run_id(): void {
		$this->setupSequence(
			[],
			[ 'graph_json' => $this->graphJson( [ $this->triggerNode() ] ) ]
		);

		$this->assertSame( 100, \zaplane_run_workflow( 1, [ 'source' => 'cron' ] ) );
	}

	public function test_global_helper_returns_false_when_automation_not_initialized(): void {
		// Temporarily clear the singleton.
		( new \ReflectionClass( Automation::class ) )
			->getProperty( 'instance' )
			->setValue( null, null );

		$result = \zaplane_run_workflow( 1, [] );

		$this->assertFalse( $result );

		// Restore so tearDown works cleanly.
		( new \ReflectionClass( Automation::class ) )
			->getProperty( 'instance' )
			->setValue( null, $this->automation );
	}

	// ── get_instance ──────────────────────────────────────────────────────────

	public function test_get_instance_returns_the_automation_singleton(): void {
		$this->assertSame( $this->automation, Automation::get_instance() );
	}

	public function test_get_instance_returns_null_before_init(): void {
		( new \ReflectionClass( Automation::class ) )
			->getProperty( 'instance' )
			->setValue( null, null );

		$this->assertNull( Automation::get_instance() );

		( new \ReflectionClass( Automation::class ) )
			->getProperty( 'instance' )
			->setValue( null, $this->automation );
	}
}
