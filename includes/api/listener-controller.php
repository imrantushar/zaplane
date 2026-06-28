<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Models\Option;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ListenerController extends WP_REST_Controller {

	protected ?Container $container = null;

	private const LISTENER_TIMEOUT = 120;
	private const POLL_INTERVAL = 1;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
	}

	public function register_routes() {
		$ns = 'zaplane/v1';

		register_rest_route($ns, '/node-listener/(?P<workflow_id>\d+)', [
			'methods' => 'GET',
			'callback' => [ $this, 'start_listener' ],
			'permission_callback' => [ $this, 'permissions' ],
		]);

		register_rest_route($ns, '/node-listener/(?P<workflow_id>\d+)/stop', [
			'methods' => 'POST',
			'callback' => [ $this, 'stop_listener' ],
			'permission_callback' => [ $this, 'permissions' ],
		]);

		register_rest_route($ns, '/node-listener/(?P<workflow_id>\d+)/status', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_listener_status' ],
			'permission_callback' => [ $this, 'permissions' ],
		]);

		register_rest_route($ns, '/node-listener/cleanup', [
			'methods' => 'POST',
			'callback' => [ $this, 'cleanup_all_listeners' ],
			'permission_callback' => [ $this, 'permissions' ],
		]);
	}

	public function cleanup_all_listeners() {
		$stateOptions = Option::where( 'option_name', 'LIKE', 'zaplane_listener_state_%' )->get();
		$hookOptions = Option::where( 'option_name', 'LIKE', 'zaplane_listener_hook_%' )->get();

		$deletedStates = 0;
		$deletedHooks = 0;

		foreach ( $stateOptions as $option ) {
			$option->delete();
			$deletedStates++;
		}

		foreach ( $hookOptions as $option ) {
			$option->delete();
			$deletedHooks++;
		}

		return [
			'status' => 'success',
			'code' => 'CLEANED',
			'message' => "Cleaned up {$deletedStates} listener states and {$deletedHooks} hook configs",
		];
	}

	public function permissions() {
		return current_user_can( 'manage_options' );
	}

	public function start_listener( $req ) {
		$workflowId = (int) $req['workflow_id'];

		$workflow = Workflow::find( $workflowId );
		if ( ! $workflow ) {
			return new WP_Error( 'not_found', 'Workflow not found', [ 'status' => 404 ] );
		}

		$version = $workflow->activeVersion();
		if ( ! $version ) {
			return new WP_Error( 'no_version', 'No active workflow version', [ 'status' => 404 ] );
		}

		$graph = $version->getGraph();
		$targetNode = null;

		foreach ( $graph['nodes'] as $node ) {
			if ( ( $node['type'] ?? '' ) === 'trigger' ) {
				$targetNode = $node;
				break;
			}
		}

		if ( ! $targetNode ) {
			return new WP_Error( 'no_trigger', 'Please save the workflow and try testing again.', [ 'status' => 404 ] );
		}

		$nodeKey = (int) $targetNode['id'];

		$hook = $targetNode['data']['hook'] ?? null;
		if ( ! $hook ) {
			return new WP_Error( 'no_hook', 'Trigger hasn’t been added yet. Please set it to continue', [ 'status' => 400 ] );
		}

		$optionName = $this->get_option_name( $workflowId );

		$existingState = $this->get_state_fresh( $optionName );
		if ( $existingState && 'listening' === $existingState['status'] ) {
			$startedAt = strtotime( $existingState['started_at'] ?? '' );
			if ( $startedAt && ( time() - $startedAt ) < self::LISTENER_TIMEOUT ) {
				return new WP_Error( 'already_listening', 'Listener is already active for this workflow', [ 'status' => 409 ] );
			}
			Option::remove( $optionName );
		}

		$initialState = [
			'status' => 'listening',
			'started_at' => current_time( 'mysql' ),
			'hook' => $hook,
			'workflow_id' => $workflowId,
			'node_key' => $nodeKey,
			'data' => null,
			'triggered_at' => null,
		];
		Option::set( $optionName, $initialState, 'no' );

		$this->register_listener_hook( $workflowId, $hook, $targetNode, $version );

		$startTime = time();

		while ( time() - $startTime < self::LISTENER_TIMEOUT ) {
			$state = $this->get_state_fresh( $optionName );

			if ( ! $state ) {
				$this->unregister_listener_hook( $workflowId );
				return [
					'status' => 'stopped',
					'code' => 'STOPPED',
					'message' => 'Listener was stopped',
					'data' => null,
				];
			}

			if ( 'stopped' === $state['status'] ) {
				$this->cleanup( $optionName, $workflowId );
				return [
					'status' => 'stopped',
					'code' => 'STOPPED',
					'message' => 'Listener was stopped',
					'data' => null,
				];
			}

			if ( 'triggered' === $state['status'] && null !== $state['data'] ) {
				$triggerData = $state['data'];
				$this->cleanup( $optionName, $workflowId );

				$result = $this->execute_triggered_workflow( $version, $targetNode, $triggerData );

				return [
					'status' => 'success',
					'code' => 'TRIGGERED',
					'data' => [
						'node' => [
							'id' => $nodeKey,
							'app' => $targetNode['data']['app'] ?? null,
							'event' => $targetNode['data']['event'] ?? null,
							'label' => $targetNode['data']['label'] ?? null,
						],
						'trigger_data' => $triggerData,
						'run' => $result,
					],
				];
			}//end if

			sleep( self::POLL_INTERVAL );
		}//end while

		$this->cleanup( $optionName, $workflowId );

		return [
			'status' => 'timeout',
			'code' => 'TIMEOUT',
			'message' => 'Listener timed out after ' . self::LISTENER_TIMEOUT . ' seconds',
			'data' => null,
		];
	}

	public function stop_listener( $req ) {
		$workflowId = (int) $req['workflow_id'];
		$optionName = $this->get_option_name( $workflowId );

		$state = $this->get_state_fresh( $optionName );

		if ( ! $state ) {
			$this->cleanup( $optionName, $workflowId );
			return [
				'status' => 'success',
				'code' => 'STOPPED',
				'message' => 'Listener stopped (was not active)',
			];
		}

		$this->cleanup( $optionName, $workflowId );

		return [
			'status' => 'success',
			'code' => 'STOPPED',
			'message' => 'Listener stopped and cleaned up successfully',
		];
	}

	public function get_listener_status( $req ) {
		$workflowId = (int) $req['workflow_id'];
		$optionName = $this->get_option_name( $workflowId );

		$state = $this->get_state_fresh( $optionName );

		if ( ! $state ) {
			return [
				'status' => 'inactive',
				'listening' => false,
			];
		}

		return [
			'status' => $state['status'],
			'listening' => 'listening' === $state['status'],
			'started_at' => $state['started_at'] ?? null,
			'hook' => $state['hook'] ?? null,
			'node_key' => $state['node_key'] ?? null,
		];
	}

	private function register_listener_hook( int $workflowId, string $hook, array $node, WorkflowVersion $version ): void {
		$hookInfoOption = 'zaplane_listener_hook_' . $workflowId;
		Option::set($hookInfoOption, [
			'hook' => $hook,
			'node' => $node,
			'version_hash' => $version->graph_hash,
			'workflow_id' => $workflowId,
		], 'no');
	}

	private function unregister_listener_hook( int $workflowId ): void {
		$hookInfoOption = 'zaplane_listener_hook_' . $workflowId;
		Option::remove( $hookInfoOption );
	}

	private function execute_triggered_workflow( WorkflowVersion $version, array $triggerNode, array $payload ): array {
		$run = Run::create([
			'workflow_version_id' => $version->id,
			'workflow_id' => $version->workflow_id,
			'status' => 'running',
			'trigger_data' => $payload,
			'start_node_key' => (int) $triggerNode['id'],
			// Mark as a test run keyed to the trigger node so the captured
			// payload is discoverable by the condition-variables picker
			// (Run::latestTestNodeRunsByWorkflow filters is_test=1 AND
			// target_node_key IN previousNodeIds). Without this the trigger's
			// fields never show up for downstream action mapping.
			'is_test' => true,
			'target_node_key' => (int) $triggerNode['id'],
			'started_at' => current_time( 'mysql' ),
		]);

		$nodeRun = NodeRun::create([
			'run_id'             => $run->id,
			'node_key'           => (int) $triggerNode['id'],
			'node_meta_json'     => [
				'app'   => $triggerNode['data']['app'] ?? null,
				'event' => $triggerNode['data']['event'] ?? null,
				'label' => $triggerNode['data']['label'] ?? null,
			],
			'parent_node_run_id' => null,
			'status'             => 'completed',
			'input_json'         => $payload,
			'output_json'        => $payload,
			'started_at'         => current_time( 'mysql' ),
			'finished_at'        => current_time( 'mysql' ),
		]);

		// Make this capture reusable as sample data in other workflows that use
		// the same trigger (app+event), so they don't have to capture again.
		\Zaplane\Framework\Core\Automation::store_trigger_sample(
			$triggerNode['data']['app'] ?? '',
			$triggerNode['data']['event'] ?? '',
			$payload
		);

		$graph      = $version->getGraph();
		$graphNodeMap = [];
		foreach ( $graph['nodes'] ?? [] as $n ) {
			$graphNodeMap[ (int) $n['id'] ] = $n;
		}

		foreach ( $graph['edges'] as $edge ) {
			if ( (int) $edge['source'] === (int) $triggerNode['id'] ) {
				$targetKey  = (int) $edge['target'];
				$targetNode = $graphNodeMap[ $targetKey ] ?? null;

				$this->container->get( 'automation' )->spawn_node_run(
					$run->id,
					$targetKey,
					$payload,
					$nodeRun->id,
					$targetNode ? [
						'app'   => $targetNode['data']['app'] ?? null,
						'event' => $targetNode['data']['event'] ?? null,
						'label' => $targetNode['data']['label'] ?? null,
					] : null
				);
			}
		}

		return [
			'run_id' => $run->id,
			'node_run_id' => $nodeRun->id,
			'input' => $payload,
			'output' => $payload,
		];
	}

	private function cleanup( string $optionName, int $workflowId ): void {
		Option::remove( $optionName );
		$this->unregister_listener_hook( $workflowId );
	}

	private function get_option_name( int $workflowId ): string {
		return 'zaplane_listener_state_' . $workflowId;
	}

	private function get_state_fresh( string $optionName ): ?array {
		$option = Option::where( 'option_name', $optionName )->fresh()->first();

		if ( ! $option ) {
			return null;
		}

		$value = $option->getValue();

		return is_array( $value ) ? $value : null;
	}
}
