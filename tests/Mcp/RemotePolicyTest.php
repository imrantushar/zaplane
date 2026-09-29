<?php

namespace Zaplane\Tests\Mcp;

use Zaplane\Authoring\Catalog;
use Zaplane\Mcp\RemotePolicy;
use Zaplane\Mcp\ToolRegistry;
use Zaplane\Tests\TestCase;

class RemotePolicyTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Catalog::flush();
	}

	private function graph( string $app, string $event ): array {
		return [
			'nodes' => [
				[
					'type' => 'trigger',
					'data' => [
						'app'   => 'wordpress',
						'event' => 'publish_post',
					],
				],
				[
					'type' => 'action',
					'data' => [
						'app'   => $app,
						'event' => $event,
					],
				],
			],
		];
	}

	/**
	 * @test
	 */
	public function administrative_actions_are_found_in_graphs_and_recipe_steps(): void {
		$this->assertSame( [ 'wordpress/activate_plugin' ], RemotePolicy::violations( $this->graph( 'wordpress', 'activate_plugin' ) ) );
		$this->assertSame( [], RemotePolicy::violations( $this->graph( 'wordpress', 'create_post' ) ) );
		$this->assertSame(
			[ 'wordpress/create_user' ],
			RemotePolicy::violations( [ 'workflows' => [ [ 'steps' => [ [ 'action' => 'wordpress.create_user' ] ] ] ] ] )
		);
	}

	/**
	 * @test
	 */
	public function mcp_tools_refuse_graphs_with_administrative_actions(): void {
		$token = [
			'user_id' => 1,
			'scopes'  => [ 'read', 'write', 'run' ],
		];

		foreach ( [ 'validate_graph', 'create_workflow' ] as $tool ) {
			try {
				ToolRegistry::call( $tool, [ 'title' => 'x', 'graph' => $this->graph( 'wordpress', 'add_user_caps' ) ], $token );
				$this->fail( $tool . ' accepted an administrative action.' );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertStringContainsString( 'wordpress/add_user_caps', $e->getMessage() );
			}
		}
	}
}
