<?php

namespace Zaplane\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DemoCommand extends Command {

	protected string $signature = 'demo';
	protected string $description = 'Create a demo workflow for testing';

	public function handle( array $args, array $assoc_args ): void {
		$this->info( '🚀 Creating demo workflow...' );

		$workflow = Workflow::create([
			'user_id' => 1,
			'title' => 'Demo: Post Publish Workflow',
			'name' => 'demo-post-publish',
			'status' => 'active',
		]);

		$this->success( "Workflow created (ID: {$workflow->id})" );

		$graph = [
			'nodes' => [
				[
					'id' => 'trigger-1',
					'type' => 'trigger',
					'position' => [
						'x' => 100,
						'y' => 100
					],
					'data' => [
						'app' => 'wordpress',
						'event' => 'publish_post',
						'label' => 'Post Published',
					],
				],
				[
					'id' => 'action-1',
					'type' => 'action',
					'position' => [
						'x' => 100,
						'y' => 250
					],
					'data' => [
						'app' => 'mailerlite',
						'action' => 'add_subscriber',
						'label' => 'Add Subscriber to MailerLite',
					],
				],
			],
			'edges' => [
				[
					'id' => 'edge-1',
					'source' => 'trigger-1',
					'target' => 'action-1',
				],
			],
		];

		$version = WorkflowVersion::create([
			'workflow_id' => $workflow->id,
			'graph_json' => $graph,
			'graph_hash' => hash( 'sha256', json_encode( $graph ) ),
			'is_active' => 1,
		]);

		$this->info( 'Trigger node: Post Published' );
		$this->info( 'Action node: Add Subscriber to MailerLite' );
		$this->info( 'Connection: trigger-1 → action-1' );

		$this->success( '✅ Demo workflow created successfully!' );
		$this->line( "  Workflow ID: {$workflow->id}" );
		$this->line( "  Version ID: {$version->id}" );
	}
}
