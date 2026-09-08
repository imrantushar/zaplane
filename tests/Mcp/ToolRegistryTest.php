<?php

namespace Zaplane\Tests\Mcp;

use Zaplane\Authoring\Catalog;
use Zaplane\Mcp\ToolRegistry;
use Zaplane\Mcp\TokenStore;
use Zaplane\Tests\TestCase;

class ToolRegistryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Catalog::flush();
	}

	/**
	 * @test
	 */
	public function every_tool_declares_a_valid_scope_and_a_complete_schema(): void {
		$definitions = ToolRegistry::definitions();

		$this->assertNotEmpty( $definitions );

		foreach ( $definitions as $name => $def ) {
			$this->assertContains( $def['scope'], TokenStore::ALL_SCOPES, $name . ' has an unknown scope.' );
			$this->assertNotEmpty( $def['description'], $name . ' has no description.' );
			$this->assertIsArray( $def['properties'], $name . ' has no properties.' );
			$this->assertIsArray( $def['required'], $name . ' has no required list.' );

			foreach ( $def['required'] as $required ) {
				$this->assertArrayHasKey(
					$required,
					$def['properties'],
					$name . ' requires "' . $required . '" but does not declare it.'
				);
			}
		}
	}

	/**
	 * @test
	 */
	public function running_a_workflow_is_the_only_tool_behind_the_run_scope(): void {
		$run = [];

		foreach ( ToolRegistry::definitions() as $name => $def ) {
			if ( TokenStore::SCOPE_RUN === $def['scope'] ) {
				$run[] = $name;
			}
		}

		$this->assertSame( [ 'run_workflow' ], $run );
	}

	/**
	 * @test
	 */
	public function tools_list_is_filtered_to_the_scopes_the_token_holds(): void {
		$readOnly = array_column( ToolRegistry::schemas( [ TokenStore::SCOPE_READ ] ), 'name' );

		$this->assertContains( 'search_capabilities', $readOnly );
		$this->assertContains( 'describe_app', $readOnly );
		$this->assertNotContains( 'create_workflow', $readOnly );
		$this->assertNotContains( 'run_workflow', $readOnly );

		$author = array_column(
			ToolRegistry::schemas( [ TokenStore::SCOPE_READ, TokenStore::SCOPE_WRITE ] ),
			'name'
		);

		$this->assertContains( 'create_workflow', $author );
		$this->assertNotContains( 'run_workflow', $author );

		$full = array_column( ToolRegistry::schemas( TokenStore::ALL_SCOPES ), 'name' );
		$this->assertContains( 'run_workflow', $full );
	}

	/**
	 * @test
	 */
	public function a_token_with_no_scopes_is_shown_no_tools(): void {
		$this->assertSame( [], ToolRegistry::schemas( [] ) );
	}

	/**
	 * @test
	 */
	public function schemas_are_well_formed_mcp_tool_definitions(): void {
		foreach ( ToolRegistry::schemas( TokenStore::ALL_SCOPES ) as $tool ) {
			$this->assertArrayHasKey( 'name', $tool );
			$this->assertArrayHasKey( 'description', $tool );
			$this->assertSame( 'object', $tool['inputSchema']['type'] );
			$this->assertIsObject( $tool['inputSchema']['properties'] );
			$this->assertIsArray( $tool['inputSchema']['required'] );
		}
	}

	/**
	 * @test
	 */
	public function scope_for_names_the_scope_and_null_for_an_unknown_tool(): void {
		$this->assertSame( TokenStore::SCOPE_READ, ToolRegistry::scope_for( 'describe_app' ) );
		$this->assertSame( TokenStore::SCOPE_WRITE, ToolRegistry::scope_for( 'create_workflow' ) );
		$this->assertSame( TokenStore::SCOPE_RUN, ToolRegistry::scope_for( 'run_workflow' ) );
		$this->assertNull( ToolRegistry::scope_for( 'drop_database' ) );
	}

	/**
	 * @test
	 */
	public function calling_an_unknown_tool_throws(): void {
		$this->expectException( \InvalidArgumentException::class );
		ToolRegistry::call( 'drop_database', [] );
	}

	/**
	 * @test
	 */
	public function search_capabilities_returns_ranked_matches_and_a_next_step(): void {
		$result = ToolRegistry::call( 'search_capabilities', [ 'query' => 'send a slack message' ] );

		$this->assertNotEmpty( $result['matches'] );
		$this->assertContains( 'slack', array_column( array_slice( $result['matches'], 0, 3 ), 'app' ) );
		$this->assertStringContainsString( 'describe_app', $result['hint'] );
	}

	/**
	 * @test
	 */
	public function search_capabilities_requires_a_query_and_rejects_a_bad_type(): void {
		try {
			ToolRegistry::call( 'search_capabilities', [] );
			$this->fail( 'Expected a missing query to throw.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertStringContainsString( 'query is required', $e->getMessage() );
		}

		$this->expectException( \InvalidArgumentException::class );
		ToolRegistry::call(
			'search_capabilities',
			[
				'query' => 'x',
				'type'  => 'sideways',
			]
		);
	}

	/**
	 * @test
	 */
	public function search_capabilities_explains_itself_when_nothing_matches(): void {
		$result = ToolRegistry::call( 'search_capabilities', [ 'query' => 'zzzzqqqxyzzy' ] );

		$this->assertSame( [], $result['matches'] );
		$this->assertStringContainsString( 'list_apps', $result['hint'] );
	}

	/**
	 * @test
	 */
	public function describe_app_returns_field_schemas_and_names_the_fix_when_wrong(): void {
		$app = ToolRegistry::call( 'describe_app', [ 'slug' => 'storeengine' ] );

		$this->assertSame( 'storeengine', $app['slug'] );
		$this->assertNotEmpty( $app['actions'] );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessageMatches( '/search_capabilities/' );
		ToolRegistry::call( 'describe_app', [ 'slug' => 'nope' ] );
	}

	/**
	 * @test
	 */
	public function list_apps_covers_both_apps_and_tools_and_can_be_filtered(): void {
		$all = ToolRegistry::call( 'list_apps', [] );
		$this->assertNotEmpty( $all['apps'] );

		$tools = ToolRegistry::call( 'list_apps', [ 'category' => 'tool' ] );
		foreach ( $tools['apps'] as $row ) {
			$this->assertSame( 'tool', $row['category'] );
		}
	}

	/**
	 * @test
	 */
	public function the_pre_scoping_tool_name_is_still_answered(): void {
		$result = ToolRegistry::call( 'list_integrations', [] );

		$this->assertNotEmpty( $result['apps'] );
	}

	/**
	 * @test
	 */
	public function validate_graph_reports_errors_without_saving_anything(): void {
		$result = ToolRegistry::call(
			'validate_graph',
			[
				'graph' => [
					'nodes' => [
						[
							'type' => 'trigger',
							'data' => [
								'app'   => 'storeengine',
								'event' => 'product_purchased',
							],
						],
						[
							'type' => 'action',
							'data' => [
								'app'   => 'storeengine',
								'event' => 'update_order_status',
								// order_id and order_status both missing.
								'config' => [],
							],
						],
					],
				],
			]
		);

		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertArrayHasKey( 'normalized_graph', $result );

		// Normalization still ran, so the caller can see what was filled in.
		$this->assertSame( '1', $result['normalized_graph']['nodes'][0]['id'] );
	}

	/**
	 * @test
	 */
	public function validate_graph_passes_a_complete_graph(): void {
		$result = ToolRegistry::call(
			'validate_graph',
			[
				'graph' => [
					'nodes' => [
						[
							'type' => 'trigger',
							'data' => [
								'app'   => 'storeengine',
								'event' => 'product_purchased',
							],
						],
						[
							'type' => 'action',
							'data' => [
								'app'    => 'storeengine',
								'event'  => 'update_order_status',
								'config' => [
									'order_id'     => '{{1.order_id}}',
									'order_status' => 'processing',
								],
							],
						],
					],
				],
			]
		);

		$this->assertTrue(
			$result['valid'],
			'Expected valid, got: ' . implode( ' || ', array_column( $result['errors'], 'message' ) )
		);
	}

	/**
	 * @test
	 */
	public function graph_taking_tools_reject_a_non_object_graph(): void {
		foreach ( [ 'validate_graph', 'create_workflow', 'update_workflow' ] as $tool ) {
			try {
				ToolRegistry::call( $tool, [ 'graph' => 'not-a-graph' ] );
				$this->fail( $tool . ' should reject a non-object graph.' );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertStringContainsString( 'graph must be an object', $e->getMessage() );
			}
		}
	}
}
