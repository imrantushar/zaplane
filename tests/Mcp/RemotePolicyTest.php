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

	private function graph( string $app, string $event, string $trigger_app = 'wordpress', string $trigger_event = 'publish_post' ): array {
		return [
			'nodes' => [
				[
					'type' => 'trigger',
					'data' => [
						'app'   => $trigger_app,
						'event' => $trigger_event,
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

	/**
	 * "User deleted" and "Plugin deactivated" are triggers that share their key
	 * with the action. Listening for one administers nothing.
	 *
	 * @test
	 */
	public function a_trigger_is_never_counted_as_an_administrative_action(): void {
		$this->assertSame( [], RemotePolicy::violations( $this->graph( 'wordpress', 'create_post', 'wordpress', 'delete_user' ) ) );
		$this->assertSame( [], RemotePolicy::violations( [ 'workflows' => [ [ 'steps' => [ [ 'trigger' => 'wordpress.deactivate_plugin' ] ] ] ] ] ) );
	}

	/**
	 * @test
	 */
	public function a_webhook_started_workflow_cannot_use_an_administrative_action(): void {
		$remote = $this->graph( 'wordpress', 'create_user', 'webhook', 'catch' );

		$this->assertSame( [ 'webhook/catch' ], RemotePolicy::remote_triggers( $remote ) );
		$this->assertSame( [ 'wordpress/create_user' ], RemotePolicy::remote_violations( $remote ) );

		// The same action from a trigger on this site is the owner's to use.
		$this->assertSame( [], RemotePolicy::remote_violations( $this->graph( 'wordpress', 'create_user' ) ) );

		// And a webhook may still start a workflow that administers nothing.
		$this->assertSame( [], RemotePolicy::remote_violations( $this->graph( 'wordpress', 'create_post', 'webhook', 'catch' ) ) );
	}

	/**
	 * @test
	 */
	public function more_remote_trigger_apps_can_be_added_but_not_removed(): void {
		$add = fn() => [ 'my_saas' ];
		add_filter( 'zaplane_remote_trigger_apps', $add );

		try {
			$this->assertTrue( RemotePolicy::is_remote_trigger_app( 'my_saas' ) );
			$this->assertTrue( RemotePolicy::is_remote_trigger_app( 'webhook' ) );
		} finally {
			remove_filter( 'zaplane_remote_trigger_apps', $add );
		}
	}
}
