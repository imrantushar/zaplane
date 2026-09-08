<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Classes\GlobalContext;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;
use Zaplane\Utils\VariableExtractor;
use Zaplane\Framework\Core\IntegrationLoader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WorkflowsController extends WP_REST_Controller {

	protected ?Container $container = null;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
	}

	public function register_routes() {
		$namespace = 'zaplane/v1';
		$rest_base = 'workflows';

		register_rest_route($namespace, '/' . $rest_base, [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_workflow_items' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		]);

		register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_workflow_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		]);

		register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)/graph', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_graph' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		]);

		register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)/versions', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [ $this, 'list_versions' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		]);

		register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)/versions/(?P<version_id>\d+)', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_version' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		]);

		register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)/versions/(?P<version_id>\d+)/activate', [
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'activate_version' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		]);

		register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)/runs', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_runs' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args' => [
					'page' => [
						'type' => 'integer',
						'default' => 1,
						'minimum' => 1,
						'sanitize_callback' => 'absint',
					],
					'per_page' => [
						'type' => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
						'sanitize_callback' => 'absint',
					],
				],
			],
		]);

		register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)/trigger', [
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'trigger_workflow' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		]);

		register_rest_route($namespace, '/condition-variables', [
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'get_condition_variables' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		]);
	}

	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Run a workflow on demand (the "Run"/Manual trigger). Starts a run from the
	 * workflow's trigger node with any posted data, via the automation engine.
	 */
	public function trigger_workflow( $request ) {
		$workflow_id = (int) $request['id'];

		$workflow = Workflow::find( $workflow_id );
		if ( ! $workflow ) {
			return new WP_Error( 'not_found', 'Workflow not found.', [ 'status' => 404 ] );
		}

		$body = $request->get_json_params();
		$data = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : [];

		$automation = $this->container ? $this->container->get( 'automation' ) : \Zaplane\Framework\Core\Automation::get_instance();
		if ( ! $automation ) {
			return new WP_Error( 'unavailable', 'Automation engine is not available.', [ 'status' => 500 ] );
		}

		$run_id = $automation->run_workflow( $workflow_id, $data );
		if ( ! $run_id ) {
			return new WP_Error(
				'no_trigger',
				'This workflow has no active version or trigger node to run.',
				[ 'status' => 422 ]
			);
		}

		return rest_ensure_response( [
			'triggered' => true,
			'run_id' => (int) $run_id
		] );
	}

	public function get_workflow_items( $request ) {
		$page = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );

		$total = Workflow::count();
		$workflows = Workflow::orderBy( 'id', 'desc' )
			->forPage( $page, $perPage )
			->get();

		$data = [];
		foreach ( $workflows as $workflow ) {
			$item = $workflow->toArray();

			$version = $workflow->activeVersion();
			if ( $version ) {
				$successRuns = Run::where( 'workflow_version_id', $version->id )
					->where( 'status', 'completed' )
					->count();
				$failedRuns = Run::where( 'workflow_version_id', $version->id )
					->where( 'status', 'failed' )
					->count();
			} else {
				$successRuns = 0;
				$failedRuns  = 0;
			}

			$item['success_runs'] = $successRuns;
			$item['failed_runs']  = $failedRuns;
			$data[] = $item;
		}

		return rest_ensure_response([
			'data' => $data,
			'pagination' => [
				'page' => $page,
				'per_page' => $perPage,
				'total' => $total,
				'total_pages' => (int) ceil( $total / $perPage ),
			],
		]);
	}

	public function get_workflow_item( $request ) {
		$workflow = Workflow::find( (int) $request['id'] );

		if ( ! $workflow ) {
			return new WP_Error( 'not_found', 'Workflow not found', [ 'status' => 404 ] );
		}

		return rest_ensure_response( $workflow->toArray() );
	}

	public function create_item( $request ) {
		$workflow = Workflow::create([
			'user_id' => get_current_user_id(),
			'title'   => sanitize_text_field( $request['title'] ),
			'status'  => 'draft',
		]);

		$emptyGraph = [
			'nodes' => [],
			'edges' => []
		];

		WorkflowVersion::create([
			'workflow_id'    => $workflow->id,
			'graph_json'     => $emptyGraph,
			'graph_hash'     => hash( 'sha256', wp_json_encode( $emptyGraph ) ),
			'is_active'      => 1,
			'version_number' => null,
		]);

		return rest_ensure_response( [ 'id' => $workflow->id ] );
	}

	public function update_item( $request ) {
		$workflowId = (int) $request['id'];
		$graph      = $request->get_json_params();
		$layout     = sanitize_text_field( $request['layout'] ?? 'LR' );

		if ( ! isset( $graph['nodes'] ) || ! isset( $graph['edges'] ) ) {
			return new WP_Error( 'invalid_graph', 'Invalid React Flow graph', [ 'status' => 400 ] );
		}

		$workflow = Workflow::find( $workflowId );

		if ( ! $workflow ) {
			return new WP_Error( 'not_found', 'Workflow not found', [ 'status' => 404 ] );
		}

		$workflow->layout = $layout;

		$icons = $graph['integration_icons'] ?? null;
		// if ( is_array( $icons ) ) {
		// $workflow->integration_icons = array_slice( array_values( array_unique( $icons ) ), 0, 3 );
		// }
		if ( is_array( $icons ) ) {
			$workflow->integration_icons = array_values( array_unique( $icons ) );
		}

		$workflow->save();

		$hash    = hash( 'sha256', wp_json_encode( $graph ) );
		$current = WorkflowVersion::where( 'workflow_id', $workflowId )->where( 'is_active', 1 )->first();

		if ( 'draft' === $workflow->status ) {
			if ( $current ) {
				if ( $current->graph_hash === $hash ) {
					return rest_ensure_response([
						'workflow_id' => $workflowId,
						'version_id'  => $current->id,
						'hash'        => $hash,
					]);
				}

				$current->graph_json = $graph;
				$current->graph_hash = $hash;
				$current->save();

				return rest_ensure_response([
					'workflow_id' => $workflowId,
					'version_id'  => $current->id,
					'hash'        => $hash,
				]);
			}

			$version = WorkflowVersion::create([
				'workflow_id'    => $workflowId,
				'graph_json'     => $graph,
				'graph_hash'     => $hash,
				'is_active'      => 1,
				'version_number' => null,
			]);

			return rest_ensure_response([
				'workflow_id' => $workflowId,
				'version_id'  => $version->id,
				'hash'        => $hash,
			]);
		}//end if

		if ( $current && $current->graph_hash === $hash ) {
			return rest_ensure_response([
				'workflow_id'    => $workflowId,
				'version_id'     => $current->id,
				'version_number' => $current->version_number,
				'hash'           => $hash,
			]);
		}

		$currentNodeIds = [];
		if ( $current ) {
			$currentGraph   = $current->getGraph();
			$currentNodeIds = array_map( fn( $n) => (string) $n['id'], $currentGraph['nodes'] ?? [] );
			sort( $currentNodeIds );
		}

		$newNodeIds = array_map( fn( $n) => (string) $n['id'], $graph['nodes'] );
		sort( $newNodeIds );

		$structuralChange = $currentNodeIds !== $newNodeIds;

		if ( ! $structuralChange && $current ) {
			$current->graph_json     = $graph;
			$current->graph_hash     = $hash;
			$current->version_number = ( $current->version_number ?? 0 ) + 1;
			$current->save();

			return rest_ensure_response([
				'workflow_id'    => $workflowId,
				'version_id'     => $current->id,
				'version_number' => $current->version_number,
				'hash'           => $hash,
			]);
		}

		if ( $current ) {
			$current->is_active = 0;
			$current->save();
		}

		$version = WorkflowVersion::create([
			'workflow_id'    => $workflowId,
			'graph_json'     => $graph,
			'graph_hash'     => $hash,
			'is_active'      => 1,
			'version_number' => 1,
		]);

		return rest_ensure_response([
			'workflow_id'    => $workflowId,
			'version_id'     => $version->id,
			'version_number' => 1,
			'hash'           => $hash,
		]);
	}

	public function delete_item( $request ) {
		$workflowId = (int) $request['id'];
		$workflow   = Workflow::find( $workflowId );

		if ( ! $workflow ) {
			return new WP_Error( 'not_found', 'Workflow not found', [ 'status' => 404 ] );
		}

		$workflow->delete();

		WorkflowVersion::where( 'workflow_id', $workflowId )->delete();

		$runIds = Run::where( 'workflow_id', $workflowId )->get()->map( fn( $r) => $r->id )->toArray();
		if ( ! empty( $runIds ) ) {
			NodeRun::whereIn( 'run_id', $runIds )->delete();
			Run::where( 'workflow_id', $workflowId )->delete();
		}

		return rest_ensure_response( [
			'deleted' => true,
			'id' => $workflowId
		] );
	}

	public function get_graph( $request ) {
		$workflow = Workflow::find( (int) $request['id'] );

		if ( ! $workflow ) {
			return new WP_Error( 'not_found', 'Workflow not found', [ 'status' => 404 ] );
		}

		$version = $workflow->activeVersion();
		$graph = $version ? $version->getGraph() : [
			'nodes' => [],
			'edges' => []
		];

		$testOutputs = [];
		if ( $version ) {
			$nodeRuns = Run::latestTestNodeRunsByWorkflow( $workflow->id );

			foreach ( $nodeRuns as $nodeKey => $nodeRun ) {
				$output = $nodeRun->getOutput();

				if ( ! is_array( $output ) ) {
					$output = [ 'value' => $output ];
				}

				$testOutputs[ $nodeKey ] = [
					'node_run_id' => $nodeRun->id,
					'run_id' => $nodeRun->run_id,
					'output' => $output,
					'variables' => VariableExtractor::extract( $output ),
					'tested_at' => $nodeRun->finished_at,
				];
			}
		}

		return rest_ensure_response([
			'workflow' => [
				'id' => $workflow->id,
				'title' => $workflow->title,
				'name' => $workflow->name,
				'status' => $workflow->status,
				'user_id' => $workflow->user_id,
				'layout' => $workflow->layout,
			],
			'version' => $version ? [
				'id' => $version->id,
				'hash' => $version->graph_hash,
				'created_at' => $version->created_at,
			] : null,
			'graph' => $graph,
			'test_outputs' => $testOutputs,
		]);
	}

	public function list_versions( $request ) {
		$page = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );
		$workflowId = (int) $request['id'];

		$total = WorkflowVersion::where( 'workflow_id', $workflowId )->count();
		$versions = WorkflowVersion::where( 'workflow_id', $workflowId )
			->orderBy( 'id', 'desc' )
			->forPage( $page, $perPage )
			->get()
			->map(fn( $v) => [
				'id' => $v->id,
				'graph_hash' => $v->graph_hash,
				'is_active' => $v->is_active,
				'created_at' => $v->created_at,
			])
			->toArray();

		return rest_ensure_response([
			'data' => $versions,
			'pagination' => [
				'page' => $page,
				'per_page' => $perPage,
				'total' => $total,
				'total_pages' => (int) ceil( $total / $perPage ),
			],
		]);
	}

	public function get_version( $request ) {
		$version = WorkflowVersion::where( 'id', (int) $request['version_id'] )
			->where( 'workflow_id', (int) $request['id'] )
			->first();

		if ( ! $version ) {
			return new WP_Error( 'not_found', 'Version not found', [ 'status' => 404 ] );
		}

		return rest_ensure_response([
			'id' => $version->id,
			'is_active' => $version->is_active,
			'graph' => $version->getGraph(),
		]);
	}

	public function activate_version( $request ) {
		$workflowId = (int) $request['id'];
		$versionId = (int) $request['version_id'];

		$version = WorkflowVersion::where( 'id', $versionId )
			->where( 'workflow_id', $workflowId )
			->first();

		if ( ! $version ) {
			return new WP_Error( 'invalid_version', 'Version not found', [ 'status' => 404 ] );
		}

		WorkflowVersion::where( 'workflow_id', $workflowId )->update( [ 'is_active' => 0 ] );

		$version->is_active = true;
		$version->save();

		do_action( 'zaplane_workflow_updated', $workflowId );

		return rest_ensure_response([
			'workflow_id' => $workflowId,
			'version_id' => $versionId,
			'is_active' => $version->is_active,
		]);
	}

	public function get_runs( $request ) {
		$workflow = Workflow::find( (int) $request['id'] );

		if ( ! $workflow ) {
			return rest_ensure_response([
				'data' => [],
				'pagination' => [
					'page' => 1,
					'per_page' => 20,
					'total' => 0,
					'total_pages' => 0
				],
			]);
		}

		$version = $workflow->activeVersion();

		if ( ! $version ) {
			return rest_ensure_response([
				'data' => [],
				'pagination' => [
					'page' => 1,
					'per_page' => 20,
					'total' => 0,
					'total_pages' => 0
				],
			]);
		}

		$page = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );

		$total = Run::where( 'workflow_version_id', $version->id )->count();
		$runs = Run::where( 'workflow_version_id', $version->id )
			->orderBy( 'id', 'desc' )
			->forPage( $page, $perPage )
			->get()
			->map(fn( $r) => [
				'id' => $r->id,
				'status' => $r->status,
				'started_at' => $r->started_at,
				'finished_at' => $r->finished_at,
				'last_error' => $r->last_error,
				'node_runs_count' => NodeRun::where( 'run_id', $r->id )->count(),
			])
			->toArray();

		return rest_ensure_response([
			'data' => $runs,
			'pagination' => [
				'page' => $page,
				'per_page' => $perPage,
				'total' => $total,
				'total_pages' => (int) ceil( $total / $perPage ),
			],
		]);
	}

	public function get_condition_variables( $request ) {
		$targetNodeKey  = (int) ( $request['target_node_key'] ?? 0 );
		$workflowId     = (int) ( $request['workflow_id'] ?? 0 );
		$workflowVersionId = (int) ( $request['workflow_version_id'] ?? 0 );
		$graph          = ! empty( $request['graph'] ) ? $request['graph'] : null;

		if ( ! $targetNodeKey ) {
			return new WP_Error( 'invalid_params', 'target_node_key is required', [ 'status' => 400 ] );
		}

		$nodes = $graph['nodes'] ?? [];
		$edges = $graph['edges'] ?? [];

		$nodeMap = [];
		foreach ( $nodes as $node ) {
			$nodeMap[ (int) $node['id'] ] = $node;
		}

		$previousNodeIds = $this->findPreviousNodes( $targetNodeKey, $edges );

		// Prefer captured output from a dedicated "Test" run, but fall back to the
		// latest real run (e.g. an actual form submission or a manual "Run") so the
		// "@" picker shows real fields the user has already produced.
		$nodeOutputs = Run::latestTestNodeRunsByWorkflow( $workflowId, $previousNodeIds );
		$realOutputs = Run::latestNodeRunsByWorkflow( $workflowId, $previousNodeIds );

		$data = [];
		foreach ( $previousNodeIds as $nodeId ) {
			$node = $nodeMap[ $nodeId ] ?? null;
			if ( ! $node ) {
				continue;
			}

			// The target node's own output is not a variable source for itself.
			if ( (int) $nodeId === $targetNodeKey ) {
				continue;
			}

			// The "type" the builder sends is the app slug for tools (csv, delay…)
			// and 'trigger'/'action' otherwise, so a fixed whitelist would drop every
			// tool node. Resolve by integration slug instead; trigger-vs-action comes
			// from the graph type / data.action.
			$nodeType = $node['type'] ?? '';

			$nodeRun = $nodeOutputs[ $nodeId ] ?? $realOutputs[ $nodeId ] ?? null;

			if ( $nodeRun ) {
				$output = $nodeRun->getOutput();

				if ( ! is_array( $output ) ) {
					$output = [ 'value' => $output ];
				}

				$data[] = [
					'node_id'    => $nodeId,
					'node_name'  => $node['data']['name'] ?? '',
					'node_event' => $node['data']['event'] ?? '',
					'variables'  => VariableExtractor::extract( $output ),
					'is_sample'  => false,
				];
			} else {
				// No test run — fall back to the integration's declared sample so
				// fields still show in the "@" picker. Nodes store their integration
				// under data.app (data.integration is a legacy fallback).
				$integration = $node['data']['app'] ?? $node['data']['integration'] ?? '';
				$event       = $node['data']['event'] ?? '';
				$instance    = ( '' !== $integration && 'Select an app' !== $integration )
					? IntegrationLoader::get( $integration )
					: null;

				$sample = [];

				if ( 'trigger' === $nodeType ) {
					// 1) Reuse the most recent real capture of this trigger from ANY
					// workflow, so a new workflow inherits its fields.
					$triggerConfig = is_array( $node['data']['config'] ?? null ) ? $node['data']['config'] : [];
					$sample        = \Zaplane\Framework\Core\Automation::get_trigger_sample( $integration, $event, $triggerConfig );

					// 2) Fall back to the integration's declared trigger sample.
					if ( empty( $sample ) && $instance ) {
						$sample = get_class( $instance )::get_trigger_sample_output( $event );
					}
				} elseif ( $instance ) {
					// Actions/tools expose their fields via the action sample.
					$sample = get_class( $instance )::get_action_sample_output( $event );
				}

				// Skip nodes that expose nothing (placeholder / Sticky Note).
				if ( empty( $sample ) ) {
					continue;
				}

				$data[] = [
					'node_id'    => $nodeId,
					'node_name'  => $node['data']['name'] ?? '',
					'node_event' => $event,
					'variables'  => VariableExtractor::extract( $sample ),
					'is_sample'  => true,
				];
			}//end if
		}//end foreach

		$workflow = Workflow::find( $workflowId );
		$context  = GlobalContext::all_for_picker( $workflow ?: null );

		return rest_ensure_response([
			'status'  => 'success',
			'code'    => 'SUCCESS',
			'data'    => $data,
			'context' => $context,
		]);
	}

	private function findPreviousNodes( int $targetNodeId, array $edges ): array {
		$previousNodes = [ $targetNodeId ];
		$queue = [ $targetNodeId ];
		$visited = [ $targetNodeId => true ];

		while ( ! empty( $queue ) ) {
			$currentId = array_shift( $queue );

			foreach ( $edges as $edge ) {
				$target = (int) $edge['target'];
				$source = (int) $edge['source'];

				if ( $target === $currentId && ! isset( $visited[ $source ] ) ) {
					$visited[ $source ] = true;
					$previousNodes[] = $source;
					$queue[] = $source;
				}
			}
		}

		return $previousNodes;
	}
}
