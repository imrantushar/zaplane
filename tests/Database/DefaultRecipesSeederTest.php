<?php

namespace Zaplane\Tests\Database;

use Zaplane\Tests\TestCase;
use Zaplane\Database\Seeders\DefaultRecipesSeeder;

class DefaultRecipesSeederTest extends TestCase {

	private const TITLE = 'WooCommerce Abandoned Cart';
	private const TABLE = 'wp_zaplane_recipes';

	protected function setUp(): void {
		parent::setUp();

		// Reset the wpdb mock so rows from one test don't bleed into the next.
		global $wpdb;
		$wpdb->reset();

		// Clear the ORM query cache so each test gets a fresh DB read.
		$reflector = new \ReflectionClass( \Zaplane\Framework\Database\ORM\QueryBuilder::class );
		$cacheProp = $reflector->getProperty( 'queryCache' );
		$cacheProp->setValue( null, [] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function runWithNoExistingRecipe(): void {
		global $wpdb;
		$wpdb->tables['results']         = [];
		$wpdb->tables['next_insert_id']  = 99;

		( new DefaultRecipesSeeder() )->run();
	}

	private function runWithExistingRecipe(): void {
		global $wpdb;
		$wpdb->tables['results'] = [
			[
				'id'    => 1,
				'title' => self::TITLE,
			],
		];

		( new DefaultRecipesSeeder() )->run();
	}

	private function getInsertedRow(): array {
		global $wpdb;
		$rows = $wpdb->tables[ self::TABLE ] ?? [];
		$this->assertNotEmpty( $rows, 'Expected a row to be inserted into ' . self::TABLE );
		return $rows[0];
	}

	private function getInsertedBlueprint(): array {
		$row       = $this->getInsertedRow();
		$blueprint = json_decode( $row['blueprint'], true );
		$this->assertIsArray( $blueprint, 'blueprint must be valid JSON' );
		return $blueprint;
	}

	private function getNodes(): array {
		$bp = $this->getInsertedBlueprint();
		return $bp['versions'][0]['graph_json']['nodes'];
	}

	private function getEdges(): array {
		$bp = $this->getInsertedBlueprint();
		return $bp['versions'][0]['graph_json']['edges'];
	}

	private function findNode( string $id ): array {
		foreach ( $this->getNodes() as $node ) {
			if ( (string) $node['id'] === $id ) {
				return $node;
			}
		}
		$this->fail( "Node with id '{$id}' not found in blueprint" );
	}

	// -------------------------------------------------------------------------
	// Creation
	// -------------------------------------------------------------------------

	public function test_seeder_creates_recipe_when_none_exists(): void {
		$this->runWithNoExistingRecipe();

		global $wpdb;
		$rows = $wpdb->tables[ self::TABLE ] ?? [];
		$this->assertCount( 1, $rows, 'Seeder should insert exactly one recipe' );
	}

	public function test_recipe_title_is_correct(): void {
		$this->runWithNoExistingRecipe();

		$row = $this->getInsertedRow();
		$this->assertSame( self::TITLE, $row['title'] );
	}

	public function test_recipe_description_is_set(): void {
		$this->runWithNoExistingRecipe();

		$row = $this->getInsertedRow();
		$this->assertNotEmpty( $row['description'] );
	}

	public function test_recipe_created_by_is_zero(): void {
		$this->runWithNoExistingRecipe();

		$row = $this->getInsertedRow();
		$this->assertSame( 0, (int) $row['created_by'] );
	}

	// -------------------------------------------------------------------------
	// Idempotency
	// -------------------------------------------------------------------------

	public function test_seeder_skips_if_recipe_already_exists(): void {
		$this->runWithExistingRecipe();

		global $wpdb;
		$rows = $wpdb->tables[ self::TABLE ] ?? [];
		$this->assertEmpty( $rows, 'Seeder must not insert a duplicate recipe' );
	}

	// -------------------------------------------------------------------------
	// Blueprint — top-level structure
	// -------------------------------------------------------------------------

	public function test_blueprint_has_one_version(): void {
		$this->runWithNoExistingRecipe();

		$bp = $this->getInsertedBlueprint();
		$this->assertCount( 1, $bp['versions'] );
	}

	public function test_blueprint_version_is_active(): void {
		$this->runWithNoExistingRecipe();

		$bp = $this->getInsertedBlueprint();
		$this->assertTrue( (bool) $bp['versions'][0]['is_active'] );
	}

	public function test_blueprint_has_graph_hash(): void {
		$this->runWithNoExistingRecipe();

		$bp   = $this->getInsertedBlueprint();
		$hash = $bp['versions'][0]['graph_hash'] ?? '';
		$this->assertNotEmpty( $hash );
		$this->assertSame( 64, strlen( $hash ), 'graph_hash should be a sha256 hex string (64 chars)' );
	}

	public function test_blueprint_connections_are_empty(): void {
		$this->runWithNoExistingRecipe();

		$bp = $this->getInsertedBlueprint();
		$this->assertSame( [], $bp['connections'] );
	}

	// -------------------------------------------------------------------------
	// Blueprint — nodes
	// -------------------------------------------------------------------------

	public function test_blueprint_has_four_nodes(): void {
		$this->runWithNoExistingRecipe();

		$this->assertCount( 4, $this->getNodes() );
	}

	public function test_all_node_ids_are_strings(): void {
		$this->runWithNoExistingRecipe();

		foreach ( $this->getNodes() as $node ) {
			$this->assertIsString( $node['id'], "Node id must be a string (got " . gettype( $node['id'] ) . " for id=" . json_encode( $node['id'] ) . ")" );
		}
	}

	public function test_trigger_node_is_correct(): void {
		$this->runWithNoExistingRecipe();

		$node = $this->findNode( '1' );
		$this->assertSame( 'trigger', $node['type'] );
		$this->assertSame( 'abandoned-cart', $node['data']['app'] );
		$this->assertSame( 'cart_abandoned', $node['data']['event'] );
	}

	public function test_first_email_node_is_correct(): void {
		$this->runWithNoExistingRecipe();

		$node   = $this->findNode( '2' );
		$config = $node['data']['config'];

		$this->assertSame( 'action', $node['type'] );
		$this->assertSame( 'gemcrm', $node['data']['app'] );
		$this->assertSame( 'send_email', $node['data']['event'] );
		$this->assertSame( 'custom', $config['recipient_type'] );
		$this->assertNotEmpty( $config['custom_email'] );
		$this->assertNotEmpty( $config['subject'] );
		$this->assertNotEmpty( $config['body'] );
	}

	public function test_first_email_recipient_references_trigger_email(): void {
		$this->runWithNoExistingRecipe();

		$config = $this->findNode( '2' )['data']['config'];
		$this->assertStringContainsString( '{{1.email}}', $config['custom_email'] );
	}

	public function test_delay_node_is_correct(): void {
		$this->runWithNoExistingRecipe();

		$node   = $this->findNode( '3' );
		$config = $node['data']['config'];

		$this->assertSame( 'action', $node['type'] );
		$this->assertSame( 'delay', $node['data']['app'] );
		$this->assertSame( 'wait', $node['data']['event'] );
		$this->assertSame( 'days', $config['unit'] );
		$this->assertSame( 3, (int) $config['amount'] );
	}

	public function test_followup_email_node_is_correct(): void {
		$this->runWithNoExistingRecipe();

		$node   = $this->findNode( '4' );
		$config = $node['data']['config'];

		$this->assertSame( 'action', $node['type'] );
		$this->assertSame( 'gemcrm', $node['data']['app'] );
		$this->assertSame( 'send_email', $node['data']['event'] );
		$this->assertSame( 'custom', $config['recipient_type'] );
		$this->assertStringContainsString( '{{1.email}}', $config['custom_email'] );
		$this->assertNotEmpty( $config['subject'] );
		$this->assertNotEmpty( $config['body'] );
	}

	public function test_followup_subject_differs_from_first_email_subject(): void {
		$this->runWithNoExistingRecipe();

		$subject1 = $this->findNode( '2' )['data']['config']['subject'];
		$subject4 = $this->findNode( '4' )['data']['config']['subject'];
		$this->assertNotSame( $subject1, $subject4, 'Follow-up email must have a different subject' );
	}

	// -------------------------------------------------------------------------
	// Blueprint — edges
	// -------------------------------------------------------------------------

	public function test_blueprint_has_three_edges(): void {
		$this->runWithNoExistingRecipe();

		$this->assertCount( 3, $this->getEdges() );
	}

	public function test_all_edge_source_and_target_are_strings(): void {
		$this->runWithNoExistingRecipe();

		foreach ( $this->getEdges() as $edge ) {
			$this->assertIsString( $edge['source'], "Edge source must be a string" );
			$this->assertIsString( $edge['target'], "Edge target must be a string" );
		}
	}

	public function test_edges_connect_nodes_sequentially(): void {
		$this->runWithNoExistingRecipe();

		$edges = $this->getEdges();

		$this->assertSame( '1', $edges[0]['source'] );
		$this->assertSame( '2', $edges[0]['target'] );

		$this->assertSame( '2', $edges[1]['source'] );
		$this->assertSame( '3', $edges[1]['target'] );

		$this->assertSame( '3', $edges[2]['source'] );
		$this->assertSame( '4', $edges[2]['target'] );
	}

	// -------------------------------------------------------------------------
	// Blueprint — graph hash integrity
	// -------------------------------------------------------------------------

	public function test_graph_hash_matches_graph_json(): void {
		$this->runWithNoExistingRecipe();

		$bp      = $this->getInsertedBlueprint();
		$version = $bp['versions'][0];
		$expected = hash( 'sha256', wp_json_encode( $version['graph_json'] ) );
		$this->assertSame( $expected, $version['graph_hash'], 'Stored graph_hash must match sha256 of graph_json' );
	}
}
