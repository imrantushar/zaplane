<?php

namespace Zaplane\Tests\Authoring;

use PHPUnit\Framework\TestCase;
use Zaplane\Authoring\Catalog;

/**
 * Runs against the real assets/json/integrations.json rather than a fixture —
 * these are the guarantees callers rely on, and they have to hold for the
 * catalogue actually shipped.
 */
class CatalogTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Catalog::flush();
	}

	/**
	 * @test
	 */
	public function it_loads_apps_and_tools_from_the_shipped_manifest(): void {
		$manifest = Catalog::manifest();

		$this->assertArrayHasKey( 'apps', $manifest );
		$this->assertArrayHasKey( 'tools', $manifest );
		$this->assertNotEmpty( $manifest['apps'], 'The shipped manifest should not be empty.' );
		$this->assertArrayHasKey( 'storeengine', $manifest['apps'] );
		$this->assertArrayHasKey( 'router', $manifest['tools'] );
	}

	/**
	 * @test
	 */
	public function it_lists_apps_and_tools_together_with_capability_counts(): void {
		$rows = Catalog::list_apps();

		$this->assertNotEmpty( $rows );

		$bySlug = array_column( $rows, null, 'slug' );

		$this->assertArrayHasKey( 'storeengine', $bySlug, 'Apps should be listed.' );
		$this->assertArrayHasKey( 'router', $bySlug, 'Tools should be listed alongside apps.' );

		$this->assertGreaterThan( 0, $bySlug['storeengine']['trigger_count'] );
		$this->assertGreaterThan( 0, $bySlug['storeengine']['action_count'] );
		$this->assertSame( 'app', $bySlug['storeengine']['category'] );
		$this->assertSame( 'tool', $bySlug['router']['category'] );
	}

	/**
	 * @test
	 */
	public function it_filters_the_list_by_category(): void {
		$tools = Catalog::list_apps( 'tool' );

		$this->assertNotEmpty( $tools );

		foreach ( $tools as $row ) {
			$this->assertSame( 'tool', $row['category'] );
		}
	}

	/**
	 * @test
	 */
	public function describe_app_returns_every_capability_with_its_field_schema(): void {
		$app = Catalog::describe_app( 'storeengine' );

		$this->assertNotNull( $app );
		$this->assertSame( 'storeengine', $app['slug'] );
		$this->assertNotEmpty( $app['triggers'] );
		$this->assertNotEmpty( $app['actions'] );

		$actions = array_column( $app['actions'], null, 'key' );
		$this->assertArrayHasKey( 'update_order_status', $actions );

		$fields = array_column( $actions['update_order_status']['schema'], 'key' );
		$this->assertContains( 'order_id', $fields );
		$this->assertContains( 'order_status', $fields );
	}

	/**
	 * @test
	 */
	public function describe_app_returns_null_for_an_unknown_slug(): void {
		$this->assertNull( Catalog::describe_app( 'not-a-real-app' ) );
	}

	/**
	 * @test
	 */
	public function describe_capability_separates_triggers_from_actions(): void {
		$trigger = Catalog::describe_capability( 'storeengine', 'trigger', 'product_purchased' );

		$this->assertNotNull( $trigger );
		$this->assertSame( 'trigger', $trigger['type'] );
		$this->assertNotSame( '', $trigger['hook'], 'A hook-driven trigger should carry its hook.' );

		// Same key, wrong bucket.
		$this->assertNull( Catalog::describe_capability( 'storeengine', 'action', 'product_purchased' ) );
	}

	/**
	 * @test
	 */
	public function search_ranks_the_matching_capability_above_unrelated_ones(): void {
		$hits = Catalog::search( 'send a slack message', 'action', 10 );

		$this->assertNotEmpty( $hits );

		$top = array_slice( $hits, 0, 3 );
		$this->assertContains(
			'slack',
			array_column( $top, 'app' ),
			'Slack should rank in the top three for "send a slack message".'
		);
	}

	/**
	 * @test
	 */
	public function search_can_be_restricted_to_one_capability_type(): void {
		foreach ( Catalog::search( 'order', 'trigger', 15 ) as $hit ) {
			$this->assertSame( 'trigger', $hit['type'] );
		}
	}

	/**
	 * @test
	 */
	public function search_respects_the_limit_and_ignores_stopword_only_queries(): void {
		$this->assertLessThanOrEqual( 5, count( Catalog::search( 'order', '', 5 ) ) );
		$this->assertSame( [], Catalog::search( 'the a of', '', 10 ) );
	}
}
