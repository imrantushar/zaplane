<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Classes\Expression;
use Zaplane\Framework\Classes\GlobalContext;
use Zaplane\Framework\Classes\TriggerNodes;
use Zaplane\Framework\Core\Automation;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;
use Zaplane\Framework\Database\ORM\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RunController extends WP_REST_Controller {

	protected ?Container $container = null;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
	}

	public function register_routes() {
		$ns = 'zaplane/v1';

		register_rest_route($ns, '/runs', [
			'methods' => 'GET',
			'callback' => [ $this, 'list_runs' ],
			'permission_callback' => [ $this, 'permissions' ],
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
				'status' => [
					'type' => 'string',
					'required' => false,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]);

		register_rest_route($ns, '/runs', [
			'methods' => 'DELETE',
			'callback' => [ $this, 'clear_runs' ],
			'permission_callback' => [ $this, 'permissions' ]
		]);

		register_rest_route($ns, '/runs/(?P<id>\d+)', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_run' ],
			'permission_callback' => [ $this, 'permissions' ]
		]);

		register_rest_route($ns, '/runs/(?P<id>\d+)', [
			'methods' => 'DELETE',
			'callback' => [ $this, 'delete_run' ],
			'permission_callback' => [ $this, 'permissions' ]
		]);

		register_rest_route($ns, '/runs/(?P<id>\d+)/replay', [
			'methods' => 'POST',
			'callback' => [ $this, 'replay_run' ],
			'permission_callback' => [ $this, 'permissions' ]
		]);

		register_rest_route($ns, '/runs/(?P<id>\d+)/stop', [
			'methods' => 'POST',
			'callback' => [ $this, 'stop_run' ],
			'permission_callback' => [ $this, 'permissions' ]
		]);

		register_rest_route($ns, '/node-runs/(?P<id>\d+)', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_node_run' ],
			'permission_callback' => [ $this, 'permissions' ]
		]);

		register_rest_route($ns, '/node-runs/(?P<id>\d+)/retry', [
			'methods' => 'POST',
			'callback' => [ $this, 'retry_node' ],
			'permission_callback' => [ $this, 'permissions' ]
		]);

		register_rest_route($ns, '/execute', [
			'methods' => 'POST',
			'callback' => [ $this, 'execute_workflow' ],
			'permission_callback' => [ $this, 'permissions' ]
		]);

		register_rest_route($ns, '/execute-node', [
			'methods' => 'POST',
			'callback' => [ $this, 'execute_single_node' ],
			'permission_callback' => [ $this, 'permissions' ]
		]);

	}

	public function permissions() {
		return current_user_can( 'manage_options' );
	}

	public function clear_runs( $request ) {
		// Logs are runs + their node-runs (no separate logs table). Delete
		// node-runs first, then the runs. An always-true WHERE satisfies the
		// ORM's "cannot delete without where clause" guard while removing all rows.
		try {
			NodeRun::query()->where( 'id', '>', 0 )->delete();
			Run::query()->where( 'id', '>', 0 )->delete();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'clear_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		return rest_ensure_response( [ 'deleted' => true ] );
	}

	public function list_runs( $request ) {
		$page    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );

		// Optional status filter (e.g. "completed", "failed", "running"). Only a
		// known status is honoured so an arbitrary value can't slip into the query.
		$status          = $request->get_param( 'status' );
		$allowedStatuses = [ 'completed', 'failed', 'running', 'cancelled', 'pending', 'waiting' ];
		$status          = in_array( $status, $allowedStatuses, true ) ? $status : null;

		$countQuery = Run::query();
		$listQuery  = Run::query()->orderBy( 'id', 'desc' );
		if ( $status ) {
			$countQuery->where( 'status', $status );
			$listQuery->where( 'status', $status );
		}

		$total = $countQuery->count();
		$runs  = $listQuery->forPage( $page, $perPage )->get();

		// Batch everything the row loop needs so it issues zero per-row queries.
		// Previously each run did its own workflowVersion() find + node-run count()
		// — ~2 queries and a graph_json decode per row (up to 100).
		$run_ids     = [];
		$version_ids = [];
		foreach ( $runs as $run ) {
			$run_ids[] = (int) $run->id;
			if ( $run->workflow_version_id ) {
				$version_ids[ (int) $run->workflow_version_id ] = true;
			}
		}
		$version_ids = array_keys( $version_ids );

		// One query for the page's distinct versions; decode each graph once
		// (many runs on a page share the same version).
		$graph_by_version    = [];
		$workflow_by_version = []; // version_id => workflow_id, so each run can link back to its workflow.
		if ( $version_ids ) {
			foreach ( WorkflowVersion::query()->whereIn( 'id', $version_ids )->get() as $version ) {
				$graph_by_version[ (int) $version->id ]    = $version->getGraph();
				$workflow_by_version[ (int) $version->id ] = (int) $version->workflow_id;
			}
		}

		// One query for the page's workflow titles (many runs share a workflow).
		$title_by_workflow = [];
		$workflow_ids      = array_values( array_unique( array_filter( $workflow_by_version ) ) );
		if ( $workflow_ids ) {
			foreach ( Workflow::query()->whereIn( 'id', $workflow_ids )->get() as $wf ) {
				$title_by_workflow[ (int) $wf->id ] = $wf->title ?: $wf->name;
			}
		}

		// One grouped query for all node-run counts on the page.
		$count_by_run = [];
		if ( $run_ids ) {
			$rows = DB::table( 'node_runs' )
				->selectRaw( 'run_id, COUNT(*) as c' )
				->whereIn( 'run_id', $run_ids )
				->groupBy( 'run_id' )
				->get();
			foreach ( $rows as $row ) {
				$count_by_run[ (int) $row['run_id'] ] = (int) $row['c'];
			}
		}

		$data = $runs->map(function ( $run ) use ( $graph_by_version, $count_by_run, $workflow_by_version, $title_by_workflow ) {
			$node  = null;
			$graph = $graph_by_version[ (int) $run->workflow_version_id ] ?? null;

			if ( is_array( $graph ) ) {
				$nodeKey = $run->start_node_key ?? $run->target_node_key;
				foreach ( $graph['nodes'] ?? [] as $n ) {
					if ( (int) $n['id'] === (int) $nodeKey ) {
						$node = $n;
						break;
					}
				}
			}

			$workflow_id = $workflow_by_version[ (int) $run->workflow_version_id ] ?? null;

			return [
				'id'             => $run->id,
				'status'         => $run->status,
				'started_at'     => $run->started_at,
				'finished_at'    => $run->finished_at,
				'node_count'     => $count_by_run[ (int) $run->id ] ?? 0,
				'workflow_id'    => $workflow_id,
				'workflow_title' => $workflow_id ? ( $title_by_workflow[ $workflow_id ] ?? null ) : null,
				'node'           => $node ? [
					'app'   => $node['data']['app'] ?? null,
					'event' => $node['data']['event'] ?? null,
				] : null,
			];
		})->toArray();

		return rest_ensure_response([
			'runs'     => $data,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $perPage,
			'pages'    => $perPage > 0 ? (int) ceil( $total / $perPage ) : 1,
		]);
	}

	public function delete_run( $req ) {
		$id  = (int) $req['id'];
		$run = Run::find( $id );

		if ( ! $run ) {
			return new WP_Error( 'not_found', 'Run not found', [ 'status' => 404 ] );
		}

		// Delete the run and its node-runs. The where() satisfies the ORM's
		// "cannot delete without where clause" guard.
		try {
			NodeRun::where( 'run_id', $id )->delete();
			$run->delete();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'delete_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		return rest_ensure_response( [
			'deleted' => true,
			'id' => $id
		] );
	}

	public function get_run( $req ) {
		$id = (int) $req['id'];
		$run = Run::find( $id );

		if ( ! $run ) {
			return new WP_Error( 'not_found', 'Run not found', [ 'status' => 404 ] );
		}

		$version = $run->workflowVersion();
		$graph = $version ? $version->getGraph() : [
			'nodes' => [],
			'edges' => []
		];

		$graphNodes = [];
		foreach ( $graph['nodes'] ?? [] as $node ) {
			$graphNodes[ (int) $node['id'] ] = $node;
		}

		$nodeRuns = NodeRun::forRun( $id )->map(function ( $nodeRun ) use ( $graphNodes ) {
			$data = $nodeRun->toArray();

			// Prefer the snapshot stored at execution time; fall back to current
			// graph for node runs created before node_meta_json was introduced.
			if ( ! empty( $nodeRun->node_meta_json ) ) {
				$data['node'] = $nodeRun->node_meta_json;
			} else {
				$graphNode    = $graphNodes[ $nodeRun->node_key ] ?? null;
				$data['node'] = $graphNode ? [
					'app'   => $graphNode['data']['app'] ?? null,
					'event' => $graphNode['data']['event'] ?? null,
					'label' => $graphNode['data']['label'] ?? null,
				] : null;
			}

			return $data;
		})->toArray();

		return [
			'run' => $run->toArray(),
			'nodes' => $nodeRuns,
		];
	}

	public function replay_run( $req ) {
		$oldId = (int) $req['id'];
		$oldRun = Run::find( $oldId );

		if ( ! $oldRun ) {
			return new WP_Error( 'not_found', 'Run not found', [ 'status' => 404 ] );
		}

		$newRun = Run::create([
			'workflow_version_id' => $oldRun->workflow_version_id,
			'workflow_id' => $oldRun->workflow_id,
			'trigger_data' => $oldRun->trigger_data,
			'status' => 'running',
			'start_node_key' => $oldRun->start_node_key,
			'target_node_key' => $oldRun->target_node_key,
			'started_at' => current_time( 'mysql' ),
		]);

		$this->container->get( 'automation' )->spawn_node_run(
			$newRun->id,
			$oldRun->start_node_key,
			$oldRun->trigger_data,
			null
		);

		return [ 'run_id' => $newRun->id ];
	}

	public function stop_run( $req ) {
		$id = (int) $req['id'];
		$run = Run::find( $id );

		if ( $run ) {
			$run->status = 'cancelled';
			$run->finished_at = current_time( 'mysql' );
			$run->save();
		}

		NodeRun::where( 'run_id', $id )
			->whereIn( 'status', [ 'pending', 'running', 'waiting' ] )
			->update([
				'status' => 'cancelled',
				'finished_at' => current_time( 'mysql' ),
			]);

		return [ 'stopped' => true ];
	}

	public function get_node_run( $req ) {
		$nodeRun = NodeRun::find( (int) $req['id'] );

		if ( ! $nodeRun ) {
			return new WP_Error( 'not_found', 'Node run not found', [ 'status' => 404 ] );
		}

		return $nodeRun->toArray();
	}

	public function retry_node( $req ) {
		$id = (int) $req['id'];
		$nodeRun = NodeRun::find( $id );

		if ( ! $nodeRun ) {
			return new WP_Error( 'not_found', 'Node run not found', [ 'status' => 404 ] );
		}

		$nodeRun->status = 'pending';
		$nodeRun->started_at = null;
		$nodeRun->finished_at = null;
		$nodeRun->output_json = null;
		$nodeRun->save();

		Automation::enqueue_node_run( $id );

		return [ 'requeued' => true ];
	}

	public function execute_workflow( $req ) {
		$workflowHash = $req['workflow_hash'];
		$data = $req['data'] ?? [];

		$version = WorkflowVersion::where( 'graph_hash', $workflowHash )->first();

		if ( ! $version ) {
			return new WP_Error( 'not_found', 'Workflow version not found', [ 'status' => 404 ] );
		}

		$graph = $version->getGraph();
		// For a workflow with several triggers, the one to start from. Without it
		// the run starts from the Manual trigger, or else the first trigger.
		$nodeId  = isset( $req['node_id'] ) && '' !== (string) $req['node_id'] ? (string) $req['node_id'] : null;
		$trigger = TriggerNodes::resolve( $graph, $nodeId );

		if ( ! $trigger ) {
			return new WP_Error( 'no_trigger', 'No trigger node found', [ 'status' => 400 ] );
		}

		$triggerKey = (int) $trigger['id'];

		$run = Run::create([
			'workflow_version_id' => $version->id,
			'workflow_id' => $version->workflow_id,
			'status' => 'running',
			'trigger_data' => $data,
			'start_node_key' => $triggerKey,
			'target_node_key' => null,
			'started_at' => current_time( 'mysql' ),
		]);

		$this->container->get( 'automation' )->spawn_node_run(
			$run->id,
			$triggerKey,
			$data,
			null
		);

		return [ 'run_id' => $run->id ];
	}

	public function execute_single_node( $req ) {
		$targetNode        = $req['target_node'] ?? null;
		$workflowVersionId = (int) ( $req['workflow_version_id'] ?? 0 );
		$input             = $req['input'] ?? [];

		// For action nodes, merge input into config so expression fields receive values.
		// Trigger nodes skip this — their input is the raw hook args, not config.
		if ( $targetNode && is_array( $input ) && 'trigger' !== ( $targetNode['type'] ?? '' ) ) {
			$targetNode['data']['config'] = array_merge( $targetNode['data']['config'] ?? [], $input );
		}

		if ( ! $workflowVersionId ) {
			return new WP_Error( 'missing_params', 'workflow_version_id is required', [ 'status' => 400 ] );
		}

		$version = WorkflowVersion::find( $workflowVersionId );

		if ( ! $version ) {
			return new WP_Error( 'not_found', 'Workflow version not found', [ 'status' => 404 ] );
		}

		if ( ! $targetNode ) {
			return new WP_Error( 'node_not_found', 'Target node not found', [ 'status' => 404 ] );
		}

		$run = Run::create([
			'workflow_version_id' => $version->id,
			'workflow_id' => $version->workflow_id,
			'status' => 'running',
			'is_test' => true,
			'trigger_data' => $input,
			'target_node_key' => (int) $targetNode['id'],
			'started_at' => current_time( 'mysql' ),
		]);

		$nodeRun = NodeRun::create([
			'run_id'             => $run->id,
			'node_key'           => (int) $targetNode['id'],
			'node_meta_json'     => [
				'app'   => $targetNode['data']['app'] ?? null,
				'event' => $targetNode['data']['event'] ?? null,
				'label' => $targetNode['data']['label'] ?? null,
			],
			'parent_node_run_id' => null,
			'status'             => 'running',
			'input_json'         => $input,
			'started_at'         => current_time( 'mysql' ),
		]);

		$connectionId = $targetNode['data']['connection_id'] ?? null;
		if ( $connectionId ) {
			try {
				$targetNode['_connection_credentials'] = $this->container
					->get( 'connections' )
					->get_execution_credentials( (int) $connectionId );
			} catch ( \Throwable $e ) {
				$e->getMessage();
			}
		}

		$testContext   = [];
		$latestTrigger = null;
		$latestRunId   = 0;
		$versionGraph  = $version->getGraph();
		$upstream      = TriggerNodes::upstream_ids( $versionGraph, (string) $targetNode['id'] );

		foreach ( Run::latestTestNodeRunsByWorkflow( $version->workflow_id ) as $nodeKey => $prevNodeRun ) {
			$out = $prevNodeRun->getOutput();

			// Match Automation::buildNodeContext()'s unwrapping — a real run only
			// ever exposes a node's inner ['data'=>..] payload to expressions, so
			// a Test Action must resolve the same {{node.field}} paths, not the
			// raw ['port'=>..,'data'=>..] the integration returned.
			if ( is_array( $out ) && isset( $out['port'], $out['data'] ) ) {
				$out = $out['data'];
			}

			$testContext[ (string) $nodeKey ] = is_array( $out ) ? $out : [ 'value' => $out ];

			// Of the triggers leading here, the one tested most recently stands in
			// for "whichever fired", so {{trigger.…}} resolves in a test as well.
			if ( in_array( (string) $nodeKey, $upstream, true ) && (int) $prevNodeRun->id > $latestRunId ) {
				$latestTrigger = (string) $nodeKey;
				$latestRunId   = (int) $prevNodeRun->id;
			}
		}

		if ( null !== $latestTrigger ) {
			$testContext = TriggerNodes::with_aliases( $testContext, $versionGraph, $latestTrigger );
		}

		// Only inject global context groups that this node's config actually references.
		// Test execution: $run = null so GlobalContext falls back to wp_get_current_user() (admin).
		// A union, not array_merge(): outputs are keyed by numeric node id, and
		// array_merge() renumbers numeric keys.
		$prefixes = GlobalContext::detect_prefixes( $targetNode['data']['config'] ?? [] );
		if ( $prefixes ) {
			$testContext = $testContext + GlobalContext::build( $prefixes );
		}

		$effectiveInput = $input + $testContext;

		if ( array_key_exists( 'trigger', $testContext ) ) {
			$effectiveInput['trigger'] = $testContext['trigger'];
		}

		try {
			if ( 'trigger' === $targetNode['type'] ) {
				$app         = strtolower( $targetNode['data']['app'] ?? '' );
				$integration = $this->container->get( 'integrations' )->get( $app );

				if ( ! $integration ) {
					throw new \Exception( 'Integration not found: ' . ( $targetNode['data']['app'] ?? 'unknown' ) );
				}

				// $input is the simulated hook args (positional array from the frontend).
				// resolve_trigger() returns the structured payload or false when filtered.
				$resolved = $integration::resolve_trigger( $targetNode['data'], array_values( $input ) );
				$output   = ( false !== $resolved ) ? TriggerNodes::apply_field_map( $targetNode, (array) $resolved ) : [];
			} else {
				$integration = $this->container->get( 'integrations' )->get( strtolower( $targetNode['data']['app'] ) );

				if ( ! $integration ) {
					throw new \Exception( 'Integration not found: ' . ( $targetNode['data']['app'] ?? 'unknown' ) );
				}

				$targetNode = $this->resolveNodeConfig( $targetNode, $effectiveInput );

				// Resolve {{...}} against the full accumulated test context above, but
				// hand execute_node only the node's own input — the same bag it gets
				// at runtime (Automation passes the parent's output, not every node's
				// output). Passing $effectiveInput here leaks the entire workflow's
				// captured context into pass-through nodes like Set Variable, so their
				// output looked identical (a static blob) on every test run.
				$output = $integration::execute_node( $targetNode, $input );
			}//end if

			$run->markAsCompleted();
			$nodeRun->setOutput( $output );

			return [
				'status' => 'success',
				'code' => 'SUCCESS',
				'data' => [
					'run_id' => $run->id,
					'node_run_id' => $nodeRun->id,
					'node' => [
						'id' => (int) $targetNode['id'],
						'app' => $targetNode['data']['app'] ?? null,
						'event' => $targetNode['data']['event'] ?? null,
						'label' => $targetNode['data']['label'] ?? null,
					],
					'input' => $input,
					'output' => $output,
				],
			];
		} catch ( \Throwable $e ) {
			$errorData = [
				'error' => $e->getMessage(),
				'error_code' => method_exists( $e, 'getErrorCode' ) ? $e->getErrorCode() : 'unknown',
			];

			if ( method_exists( $e, 'getContext' ) ) {
				$errorData['context'] = $e->getContext();
			}

			$nodeRun->output_json = $errorData;
			$nodeRun->status = 'failed';
			$nodeRun->finished_at = current_time( 'mysql' );
			$nodeRun->save();

			$run->status = 'failed';
			$run->last_error = $e->getMessage();
			$run->finished_at = current_time( 'mysql' );
			$run->save();

			return [
				'status' => 'error',
				'code' => $errorData['error_code'],
				'message' => $e->getMessage(),
				'data' => [
					'run_id' => $run->id,
					'node_run_id' => $nodeRun->id,
					'node' => [
						'id' => (int) $targetNode['id'],
						'app' => $targetNode['data']['app'] ?? null,
						'event' => $targetNode['data']['event'] ?? null,
						'label' => $targetNode['data']['label'] ?? null,
					],
					'input' => $input,
					'output' => $errorData,
				],
			];
		}//end try
	}

	private function resolveNodeConfig( array $node, array $data ): array {
		if ( ! isset( $node['data']['config'] ) || ! is_array( $node['data']['config'] ) ) {
			return $node;
		}

		$node['data']['config'] = $this->resolveConfigValues( $node['data']['config'], $data );

		return $node;
	}

	private function resolveConfigValues( $value, array $data ) {
		if ( is_string( $value ) ) {
			return Expression::evaluate( $value, $data );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = $this->resolveConfigValues( $v, $data );
			}
			return $value;
		}

		return $value;
	}
}
