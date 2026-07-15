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

		// Short-poll: the client calls this ~once a second while listening. Each
		// call returns fast (a single state read) instead of holding a worker.
		register_rest_route($ns, '/node-listener/(?P<workflow_id>\d+)/poll', [
			'methods' => 'GET',
			'callback' => [ $this, 'poll_listener' ],
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

	/**
	 * Begin listening for the trigger's real WordPress event. Returns immediately
	 * after registering — the client then short-polls poll_listener() ~once a
	 * second. (It used to block here for up to 120s, which held a PHP worker and,
	 * with any plugin-started PHP session, serialized the whole browser.)
	 */
	public function start_listener( $req ) {
		$workflowId = (int) $req['workflow_id'];

		$resolved = $this->resolve_trigger( $workflowId );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		[ $version, $targetNode ] = $resolved;

		$hook       = $targetNode['data']['hook'];
		$optionName = $this->get_option_name( $workflowId );

		$existingState = $this->get_state_fresh( $optionName );
		if ( $existingState && 'listening' === $existingState['status'] ) {
			$startedAt = strtotime( $existingState['started_at'] ?? '' );
			if ( $startedAt && ( time() - $startedAt ) < self::LISTENER_TIMEOUT ) {
				// Already listening — idempotent; just acknowledge.
				return [
					'status' => 'listening',
					'code'   => 'LISTENING',
				];
			}
			Option::remove( $optionName );
		}

		$initialState = [
			'status' => 'listening',
			'started_at' => current_time( 'mysql' ),
			'hook' => $hook,
			'workflow_id' => $workflowId,
			'node_key' => (int) $targetNode['id'],
			'data' => null,
			'triggered_at' => null,
		];
		Option::set( $optionName, $initialState, 'no' );
		// Autoloaded flag so Automation::dispatch_active_listeners can skip its
		// wp_options LIKE scan on every request when nobody is listening.
		update_option( 'zaplane_listeners_active', 1 );

		$this->register_listener_hook( $workflowId, $hook, $targetNode, $version );

		return [
			'status' => 'listening',
			'code'   => 'LISTENING',
		];
	}

	/**
	 * One fast poll of the listener state. Returns 'listening' while waiting, and
	 * a terminal status once the event fires (executing the workflow then),
	 * stops, or times out. No blocking, no sleep — the client drives the cadence.
	 */
	public function poll_listener( $req ) {
		$workflowId = (int) $req['workflow_id'];
		$optionName = $this->get_option_name( $workflowId );

		$state = $this->get_state_fresh( $optionName );

		if ( ! $state || 'stopped' === $state['status'] ) {
			$this->cleanup( $optionName, $workflowId );
			return [
				'status' => 'stopped',
				'code'   => 'STOPPED',
				'data'   => null,
			];
		}

		if ( 'triggered' === $state['status'] && null !== $state['data'] ) {
			$triggerData = $state['data'];
			$this->cleanup( $optionName, $workflowId );

			$resolved = $this->resolve_trigger( $workflowId );
			if ( is_wp_error( $resolved ) ) {
				// Workflow changed since listening started — still surface the
				// captured payload so the user sees the trigger data.
				return [
					'status'  => 'success',
					'code'    => 'TRIGGERED',
					'message' => 'Trigger fired — data captured.',
					'data'    => [ 'trigger_data' => $triggerData ],
				];
			}
			[ $version, $targetNode ] = $resolved;
			$result = $this->execute_triggered_workflow( $version, $targetNode, $triggerData );

			return [
				'status'  => 'success',
				'code'    => 'TRIGGERED',
				'message' => 'Trigger fired — data captured.',
				'data'    => [
					'node' => [
						'id'    => (int) $targetNode['id'],
						'app'   => $targetNode['data']['app'] ?? null,
						'event' => $targetNode['data']['event'] ?? null,
						'label' => $targetNode['data']['label'] ?? null,
					],
					'trigger_data' => $triggerData,
					'run'          => $result,
				],
			];
		}//end if

		// Still listening — enforce the overall timeout server-side too.
		$startedAt = strtotime( $state['started_at'] ?? '' );
		if ( $startedAt && ( time() - $startedAt ) >= self::LISTENER_TIMEOUT ) {
			$this->cleanup( $optionName, $workflowId );
			return [
				'status'  => 'timeout',
				'code'    => 'TIMEOUT',
				'message' => 'Listener timed out after ' . self::LISTENER_TIMEOUT . ' seconds',
				'data'    => null,
			];
		}

		return [
			'status' => 'listening',
			'code'   => 'LISTENING',
			'data'   => null,
		];
	}

	/**
	 * Resolve a workflow's active version and its trigger node, validating that
	 * it's saved and has a hook. Shared by start_listener and poll_listener.
	 *
	 * @return array{0:WorkflowVersion,1:array}|WP_Error
	 */
	private function resolve_trigger( int $workflowId ) {
		$workflow = Workflow::find( $workflowId );
		if ( ! $workflow ) {
			return new WP_Error( 'not_found', 'Workflow not found', [ 'status' => 404 ] );
		}

		$version = $workflow->activeVersion();
		if ( ! $version ) {
			return new WP_Error( 'no_version', 'No active workflow version', [ 'status' => 404 ] );
		}

		$targetNode = null;
		foreach ( $version->getGraph()['nodes'] as $node ) {
			if ( ( $node['type'] ?? '' ) === 'trigger' ) {
				$targetNode = $node;
				break;
			}
		}

		if ( ! $targetNode ) {
			return new WP_Error( 'no_trigger', 'Please save the workflow and try testing again.', [ 'status' => 404 ] );
		}

		if ( empty( $targetNode['data']['hook'] ) ) {
			return new WP_Error( 'no_hook', 'Trigger hasn’t been added yet. Please set it to continue', [ 'status' => 400 ] );
		}

		return [ $version, $targetNode ];
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
