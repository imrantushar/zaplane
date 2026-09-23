<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Classes\GlobalContext;
use Zaplane\Framework\Classes\TriggerNodes;
use Zaplane\Authoring\TriggerAdvisor;
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

		// The fields each trigger provides, for matching one trigger's fields to
		// another's in a workflow that has several.
		register_rest_route($namespace, '/trigger-fields', [
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'get_trigger_fields' ],
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
		// For a workflow with several triggers, the one to start from. Without it
		// the run starts from the Manual trigger, or else the first trigger.
		$node = isset( $body['node_id'] ) && '' !== (string) $body['node_id'] ? (string) $body['node_id'] : null;

		$automation = $this->container ? $this->container->get( 'automation' ) : \Zaplane\Framework\Core\Automation::get_instance();
		if ( ! $automation ) {
			return new WP_Error( 'unavailable', 'Automation engine is not available.', [ 'status' => 500 ] );
		}

		$run_id = $automation->run_workflow( $workflow_id, $data, $node );
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

		// Filters: status (active | paused | draft), folder (an id, or "none"
		// for workflows in no folder) and a search on the title.
		$status = sanitize_key( (string) ( $request->get_param( 'status' ) ?? '' ) );
		$status = in_array( $status, [ 'active', 'paused', 'draft' ], true ) ? $status : '';
		$folder = (string) ( $request->get_param( 'folder' ) ?? '' );
		$search = trim( sanitize_text_field( (string) ( $request->get_param( 'search' ) ?? '' ) ) );

		// A fresh query each time: count() rewrites the one it runs on.
		$query = static function ( bool $with_status = true ) use ( $status, $folder, $search ) {
			$q = Workflow::query();
			if ( 'none' === $folder ) {
				$q->whereRaw( '(folder_id IS NULL OR folder_id = 0)' );
			} elseif ( '' !== $folder && (int) $folder > 0 ) {
				$q->where( 'folder_id', (int) $folder );
			}
			if ( '' !== $search ) {
				global $wpdb;
				$q->where( 'title', 'LIKE', '%' . $wpdb->esc_like( $search ) . '%' );
			}
			if ( $with_status && '' !== $status ) {
				$q->where( 'status', $status );
			}
			return $q;
		};

		$total     = $query()->count();
		$workflows = $query()->orderBy( 'id', 'desc' )
			->forPage( $page, $perPage )
			->get();

		// For the status tabs: how many match the other filters, per status.
		$counts = [ 'all' => $query( false )->count() ];
		foreach ( [ 'active', 'paused', 'draft' ] as $one ) {
			$counts[ $one ] = $query( false )->where( 'status', $one )->count();
		}

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
			'counts' => $counts,
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
		$userId = get_current_user_id();
		$title  = sanitize_text_field( $request['title'] ?? '' );

		if ( empty( $title ) ) {
			return new WP_Error( 'missing_title', 'Workflow title is required', [ 'status' => 400 ] );
		}

		// Prevent rapid duplicate creation (within 2 seconds for same title and user)
		$existing = Workflow::where( 'title', $title )
			->where( 'user_id', $userId )
			->where( 'created_at', '>=', gmdate( 'Y-m-d H:i:s', time() - 2 ) )
			->orderBy( 'id', 'desc' )
			->first();

		if ( $existing ) {
			return rest_ensure_response( [ 'id' => $existing->id ] );
		}

		$workflow = Workflow::create([
			'user_id' => $userId,
			'title'   => $title,
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

	/**
	 * Save the canvas graph, and report what looks wrong with its triggers so the
	 * editor can show it. The warnings never block the save.
	 */
	public function update_item( $request ) {
		$response = $this->save_graph( $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$graph = $request->get_json_params();
		$data  = (array) $response->get_data();

		$data['warnings'] = TriggerAdvisor::warnings( is_array( $graph ) ? $graph : [] );
		$response->set_data( $data );

		return $response;
	}

	private function save_graph( $request ) {
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

				// Match Automation::buildNodeContext()'s unwrapping — see the same
				// fix in get_condition_variables() and execute_single_node().
				if ( is_array( $output ) && isset( $output['port'], $output['data'] ) ) {
					$output = $output['data'];
				}

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
			'warnings' => TriggerAdvisor::warnings( $graph ),
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

		$graph = $version->getGraph();

		$page = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );

		$total = Run::where( 'workflow_version_id', $version->id )->count();
		$runs = Run::where( 'workflow_version_id', $version->id )
			->orderBy( 'id', 'desc' )
			->forPage( $page, $perPage )
			->get();

		// A run must keep the trigger metadata it started with. Resolving the
		// label only from the current graph makes historical rows all show the
		// current trigger after a workflow is edited.
		$run_ids           = $runs->map( fn( $run ) => (int) $run->id )->toArray();
		$trigger_by_run    = [];
		$node_count_by_run = [];
		if ( $run_ids ) {
			foreach ( NodeRun::whereIn( 'run_id', $run_ids )->orderBy( 'id', 'asc' )->get() as $node_run ) {
				$run_id = (int) $node_run->run_id;
				$node_count_by_run[ $run_id ] = ( $node_count_by_run[ $run_id ] ?? 0 ) + 1;
				if ( ! isset( $trigger_by_run[ $run_id ] ) && is_array( $node_run->node_meta_json ) ) {
					$trigger_by_run[ $run_id ] = $node_run->node_meta_json;
				}
			}
		}

		$data = $runs->map(function ( $run ) use ( $graph, $trigger_by_run, $node_count_by_run ) {
			return [
				'id' => $run->id,
				'status' => $run->status,
				'started_at' => $run->started_at,
				'finished_at' => $run->finished_at,
				'last_error' => $run->last_error,
				'node_runs_count' => $node_count_by_run[ (int) $run->id ] ?? 0,
				'trigger' => isset( $trigger_by_run[ (int) $run->id ] )
					? self::run_trigger_from_meta( $graph, $trigger_by_run[ (int) $run->id ], $run->start_node_key, $run->trigger_data )
					: self::run_trigger( $graph, $run->start_node_key ),
			];
		})->toArray();

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

				// Match Automation::buildNodeContext()'s unwrapping exactly — that's
				// the shape expressions actually resolve against at runtime, so the
				// "@" picker must offer the same paths it inserts, not the raw
				// ['port'=>..,'data'=>..] the integration returned.
				if ( is_array( $output ) && isset( $output['port'], $output['data'] ) ) {
					$output = $output['data'];
				}

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

		// A step that more than one trigger leads to can read {{trigger.…}}, the
		// data of whichever trigger fired. Offer it as its own group.
		$triggerGroup = self::merged_trigger_group( is_array( $graph ) ? $graph : [], $nodeMap, $data );
		if ( $triggerGroup ) {
			$context = [ 'trigger' => $triggerGroup ] + $context;
		}

		return rest_ensure_response([
			'status'  => 'success',
			'code'    => 'SUCCESS',
			'data'    => $data,
			'context' => $context,
		]);
	}

	/**
	 * The fields each requested trigger provides: what its last test captured,
	 * else what a real run produced, else the sample shared by workflows using the
	 * same trigger, else the integration's declared sample. The graph comes from
	 * the canvas, so a trigger that hasn't been saved yet still resolves.
	 */
	public function get_trigger_fields( $request ) {
		$workflowId = (int) ( $request['workflow_id'] ?? 0 );
		$graph      = is_array( $request['graph'] ?? null ) ? $request['graph'] : [];
		$nodeIds    = array_values( array_filter( array_map( 'strval', (array) ( $request['node_ids'] ?? [] ) ), 'ctype_digit' ) );
		$keys       = array_map( 'intval', $nodeIds );

		$testOutputs = $workflowId && $keys ? Run::latestTestNodeRunsByWorkflow( $workflowId, $keys ) : [];
		$realOutputs = $workflowId && $keys ? Run::latestNodeRunsByWorkflow( $workflowId, $keys ) : [];

		$fields = [];
		foreach ( $nodeIds as $nodeId ) {
			$node = TriggerNodes::find( $graph, $nodeId );
			if ( ! $node ) {
				continue;
			}

			$fields[ $nodeId ] = $this->trigger_variables( $node, $testOutputs[ (int) $nodeId ] ?? $realOutputs[ (int) $nodeId ] ?? null );
		}

		return rest_ensure_response([
			'status' => 'success',
			'data'   => (object) $fields,
		]);
	}

	/**
	 * @param array<string,mixed> $node
	 * @param NodeRun|null        $nodeRun The trigger's latest captured run, if any.
	 * @return array<string,mixed>
	 */
	private function trigger_variables( array $node, $nodeRun ): array {
		if ( $nodeRun ) {
			$output = $nodeRun->getOutput();

			return [
				'node_id'   => (string) $node['id'],
				'variables' => VariableExtractor::extract( is_array( $output ) ? $output : [ 'value' => $output ] ),
				'is_sample' => false,
			];
		}

		$sample = [];

		if ( TriggerNodes::is_configured( $node ) ) {
			$app    = (string) $node['data']['app'];
			$event  = (string) $node['data']['event'];
			$config = is_array( $node['data']['config'] ?? null ) ? $node['data']['config'] : [];
			$sample = \Zaplane\Framework\Core\Automation::get_trigger_sample( $app, $event, $config );

			$instance = IntegrationLoader::get( $app );
			if ( empty( $sample ) && $instance ) {
				$sample = get_class( $instance )::get_trigger_sample_output( $event );
			}
		}

		return [
			'node_id'   => (string) $node['id'],
			'variables' => VariableExtractor::extract( is_array( $sample ) ? $sample : [] ),
			'is_sample' => true,
		];
	}

	/**
	 * One "Trigger" group for the picker, merging the fields of every trigger that
	 * leads to the step. Only offered with more than one such trigger; a single
	 * trigger's own group already says everything. A field that some of those
	 * triggers don't provide carries `only_from`, naming the ones that do. Fields
	 * a trigger matches to another count as provided by it.
	 *
	 * @param array<string,mixed>            $graph
	 * @param array<int,array<string,mixed>> $nodeMap
	 * @param array<int,array<string,mixed>> $data    The picker's per-node entries.
	 * @return array<string,mixed>|null
	 */
	private static function merged_trigger_group( array $graph, array $nodeMap, array $data ): ?array {
		$triggers = array_values(
			array_filter( $data, static fn( $entry ) => 'trigger' === ( $nodeMap[ (int) $entry['node_id'] ]['type'] ?? '' ) )
		);

		if ( count( $triggers ) < 2 ) {
			return null;
		}

		$fields  = [];
		$sources = [];

		foreach ( $triggers as $entry ) {
			$node  = $nodeMap[ (int) $entry['node_id'] ];
			$id    = (string) $node['id'];
			$label = TriggerNodes::label( $node );
			/* translators: %d: the trigger's number on the canvas */
			$name = sprintf( __( 'Trigger %d', 'zaplane' ), TriggerNodes::number( $graph, $id ) ) . ( '' === $label ? '' : ' (' . $label . ')' );
			$keys = [];

			foreach ( (array) ( $entry['variables'] ?? [] ) as $variable ) {
				$key = (string) ( $variable['key'] ?? '' );
				if ( '' === $key ) {
					continue;
				}

				$fields[ $key ] = $fields[ $key ] ?? $variable;
				$keys[ $key ]   = true;
			}

			foreach ( array_keys( (array) ( $node['data']['field_map']['fields'] ?? [] ) ) as $mapped ) {
				$keys[ (string) $mapped ] = true;
			}

			foreach ( array_keys( $keys ) as $key ) {
				$sources[ (string) $key ][ $id ] = $name;
			}
		}//end foreach

		$variables = [];
		foreach ( $fields as $key => $variable ) {
			$from = array_values( $sources[ (string) $key ] ?? [] );

			if ( count( $from ) < count( $triggers ) ) {
				$variable['only_from'] = $from;
			}

			$variables[] = $variable;
		}

		return [
			'label'     => __( 'Trigger (whichever fired)', 'zaplane' ),
			'prefix'    => 'trigger',
			'variables' => $variables,
		];
	}

	/**
	 * Which trigger started a run, for the Logs list.
	 *
	 * @param array<string,mixed> $graph
	 * @param int|null            $startNodeKey
	 * @return array<string,mixed>|null
	 */
	private static function run_trigger( array $graph, $startNodeKey ): ?array {
		$node = null === $startNodeKey ? null : TriggerNodes::find( $graph, $startNodeKey );

		if ( ! $node ) {
			return null;
		}

		return [
			'node_id' => (string) $node['id'],
			'number'  => TriggerNodes::number( $graph, $node['id'] ),
			'app'     => $node['data']['app'] ?? null,
			'event'   => $node['data']['event'] ?? null,
			'icon'    => $node['data']['icon'] ?? null,
			'label'   => TriggerNodes::label( $node ),
		];
	}

	/**
	 * Build a historical trigger label from the metadata captured with its run.
	 *
	 * @param array<string,mixed> $graph
	 * @param array<string,mixed> $meta
	 * @param int|null             $startNodeKey
	 * @param array<string,mixed>  $triggerData
	 * @return array<string,mixed>|null
	 */
	private static function run_trigger_from_meta( array $graph, array $meta, $startNodeKey, $triggerData = [] ): ?array {
		$nodeId = null === $startNodeKey ? null : (string) $startNodeKey;
		$event  = is_array( $triggerData ) ? (string) ( $triggerData['mailchimp_event_name'] ?? '' ) : '';
		$label  = $meta['label'] ?? null;

		if ( 'mailchimp' === strtolower( (string) ( $meta['app'] ?? '' ) ) && '' !== $event ) {
			$labels = [
				'subscribed'     => 'Subscribed',
				'unsubscribed'   => 'Unsubscribed',
				'profile_updated' => 'Profile Updated',
				'cleaned'        => 'Cleaned',
				'email_changed'  => 'Subscriber Email Changed',
				'campaign_sent'  => 'Campaign Sent',
			];
			$label = $labels[ $event ] ?? ucwords( str_replace( '_', ' ', $event ) );
		}

		return [
			'node_id' => $nodeId,
			'number'  => null === $nodeId ? null : TriggerNodes::number( $graph, $nodeId ),
			'app'     => $meta['app'] ?? null,
			'event'   => $meta['event'] ?? null,
			'icon'    => $meta['icon'] ?? null,
			'label'   => $label,
		];
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
