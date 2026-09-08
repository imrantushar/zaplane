<?php

namespace Zaplane\Framework\Core;

use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Framework\Classes\Expression;
use Zaplane\Framework\Classes\GlobalContext;
use Zaplane\Framework\Classes\Query;
use Zaplane\Framework\Exceptions\WorkflowException;
use Zaplane\Framework\Exceptions\IntegrationException;
use Zaplane\Framework\Models\Option;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;
use Zaplane\Models\WorkflowVersion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Automation {

	protected static ?self $instance = null;
	protected Container $container;
	protected array $registered_hooks = [];

	public static function init( Container $container ): self {
		if ( ! self::$instance ) {
			self::$instance = new self( $container );
			self::$instance->boot();
		}
		return self::$instance;
	}

	public function __construct( Container $container ) {
		$this->container = $container;
	}

	public function boot(): void {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		add_action( 'init', [ $this, 'dispatch_active_triggers' ] );
		add_action( 'init', [ $this, 'dispatch_active_listeners' ] );
		add_action( 'zaplane_execute_node_run', [ $this, 'dispatch_node_run' ], 10, 1 );
		add_action( 'zaplane_workflow_updated', [ $this, 'reload_triggers' ] );
		add_action( 'zaplane_resume_delayed_run', [ $this, 'resume_delayed_run' ], 10, 4 );
	}

	public function reload_triggers() {
		Query::flush_trigger_map();
		foreach ( $this->registered_hooks as $event => $cb ) {
			if ( is_array( $cb ) ) {
				remove_action( $event, $cb, 10 );
			}
		}
		$this->registered_hooks = [];
		$this->dispatch_active_triggers();
		$this->dispatch_active_listeners();
	}

	public function dispatch_active_triggers(): void {
		foreach ( Query::get_active_trigger_events() as $event ) {
			if ( is_string( $event ) && ! isset( $this->registered_hooks[ $event ] ) ) {
				$cb = [ $this, 'trigger_router' ];
				add_action( $event, $cb, 10, 99 );
				$this->registered_hooks[ $event ] = $cb;
			}
		}
	}

	public function dispatch_active_listeners(): void {
		// Listeners only exist transiently while a user is in "Test Trigger" mode.
		// The autoloaded flag lets the common case (nobody listening) skip the
		// wp_options LIKE scan entirely on every request. It's set when a listener
		// starts (ListenerController) and self-heals to 0 below once none remain.
		if ( ! get_option( 'zaplane_listeners_active' ) ) {
			return;
		}

		$listeners     = Option::where( 'option_name', 'LIKE', 'zaplane_listener_state_%' )->get();
		$any_listening = false;

		foreach ( $listeners as $listener ) {
			$state = $listener->getValue();

			if ( ! $state || ! is_array( $state ) || ( $state['status'] ?? '' ) !== 'listening' ) {
				continue;
			}

			$any_listening = true;

			$hook = $state['hook'] ?? '';
			if ( ! $hook ) {
				continue;
			}

			if ( ! isset( $this->registered_hooks[ 'listener_' . $hook ] ) ) {
				add_action( $hook, [ $this, 'listener_hook_handler' ], 1, 99 );
				$this->registered_hooks[ 'listener_' . $hook ] = true;
			}
		}

		if ( ! $any_listening ) {
			update_option( 'zaplane_listeners_active', 0 );
		}
	}

	public function listener_hook_handler() {
		$currentHook = current_filter();
		$args = func_get_args();

		$listeners = Option::where( 'option_name', 'LIKE', 'zaplane_listener_state_%' )->get();

		foreach ( $listeners as $listener ) {
			$state = $listener->getValue();

			if ( ! $state || ! is_array( $state ) || ( $state['status'] ?? '' ) !== 'listening' ) {
				continue;
			}

			if ( ( $state['hook'] ?? '' ) !== $currentHook ) {
				continue;
			}

			$workflowId = $state['workflow_id'] ?? 0;
			$hookInfoOption = 'zaplane_listener_hook_' . $workflowId;
			$hookInfo = Option::get( $hookInfoOption );

			if ( ! $hookInfo || ! is_array( $hookInfo ) ) {
				continue;
			}

			$node = $hookInfo['node'];

			$integration = $this->container->get( 'integrations' )->get( strtolower( $node['data']['app'] ) );
			if ( ! $integration ) {
				continue;
			}

			$payload = $integration::resolve_trigger( $node['data'], $args );
			if ( ! $payload ) {
				continue;
			}

			$state['status'] = 'triggered';
			$state['data'] = $payload;
			$state['triggered_at'] = current_time( 'mysql' );

			Option::set( $listener->option_name, $state, 'no' );
		}//end foreach
	}

	public function trigger_router() {
		$event = current_filter();

		$args = func_get_args();
		foreach ( Query::get_active_workflows_for_event( $event ) as $trigger ) {
			$listenerState = Option::get( 'zaplane_listener_state_' . $trigger['workflow_id'] );
			if ( is_array( $listenerState ) && ( $listenerState['status'] ?? '' ) === 'listening' ) {
				continue;
			}

			$integration = $this->container->get( 'integrations' )->get( strtolower( $trigger['app'] ) );
			// A workflow can outlive its integration (plugin removed, custom app
			// deleted). Without this the whole hook fataled, taking down every
			// other workflow listening on the same event.
			if ( ! $integration ) {
				continue;
			}

			$payload = $integration::resolve_trigger( $trigger['graph_node']['data'], $args );
			if ( ! $payload ) {
				continue;
			}

			$this->start_trigger_run( $trigger, $payload );
		}
	}

	protected function start_trigger_run( array $trigger, array $payload ): int {
		$nodeKey = (int) $trigger['id'];

		$run = Run::create([
			'workflow_version_id' => $trigger['workflow_version_id'],
			'workflow_id'         => $trigger['workflow_id'],
			// Capture the current WordPress user synchronously at hook-fire time.
			// Action Scheduler runs nodes async (no logged-in user), so we store the
			// user ID here and re-hydrate it in GlobalContext::build_wp() at execution time.
			'trigger_data'        => array_merge( $payload, [ '__wp_user_id' => get_current_user_id() ] ),
			'status'              => 'running',
			'start_node_key'      => $nodeKey,
			'target_node_key'     => null,
			'started_at'          => current_time( 'mysql' ),
		]);

		// NodeRun gets the clean payload — __wp_user_id must not appear in node outputs.
		$this->spawn_node_run( $run->id, $nodeKey, $payload, null, $this->extract_node_meta( $trigger['graph_node'] ) );

		// Remember this payload as the shared sample for the trigger (app+event
		// +config) so other workflows using the exact same trigger can map its
		// fields without re-capturing.
		$gnode = $trigger['graph_node']['data'] ?? [];
		self::store_trigger_sample( $gnode['app'] ?? '', $gnode['event'] ?? '', $payload, is_array( $gnode['config'] ?? null ) ? $gnode['config'] : [] );

		return $run->id;
	}

	/**
	 * Persist the most recent payload for a trigger, keyed by app+event (NOT by
	 * workflow), so any workflow that uses the same trigger can reuse it as
	 * sample data for field mapping without capturing again.
	 *
	 * $config is the trigger node's saved config (e.g. the selected form_id for
	 * a form-builder trigger). Triggers whose payload shape depends on which
	 * specific entity is selected (different forms have different fields) must
	 * not share a sample across different selections — folding $config into the
	 * key keeps "Form A" and "Form B" from bleeding into each other's picker.
	 */
	public static function store_trigger_sample( string $app, string $event, array $payload, array $config = [] ): void {
		$app   = sanitize_key( $app );
		$event = sanitize_key( $event );
		if ( '' === $app || '' === $event || empty( $payload ) ) {
			return;
		}
		Option::set( self::trigger_sample_option_name( $app, $event, $config ), $payload, 'no' );
	}

	/**
	 * Read the shared sample payload previously captured for a trigger
	 * (app+event+config). Returns [] when nothing has been captured for it yet.
	 */
	public static function get_trigger_sample( string $app, string $event, array $config = [] ): array {
		$app   = sanitize_key( $app );
		$event = sanitize_key( $event );
		if ( '' === $app || '' === $event ) {
			return [];
		}
		$value = Option::get( self::trigger_sample_option_name( $app, $event, $config ) );
		return is_array( $value ) ? $value : [];
	}

	/**
	 * Triggers with no config selector keep the plain app__event key (no change
	 * for the common case). A non-empty config appends a short hash of it so
	 * different selections (e.g. different forms) get distinct cache entries.
	 */
	private static function trigger_sample_option_name( string $app, string $event, array $config ): string {
		$name = 'zaplane_trigger_sample_' . $app . '__' . $event;
		if ( ! empty( $config ) ) {
			ksort( $config );
			$name .= '__' . substr( md5( (string) wp_json_encode( $config ) ), 0, 12 );
		}
		return $name;
	}

	public static function get_instance(): ?self {
		return self::$instance;
	}

	/**
	 * Directly start runs for every active workflow that listens on $event,
	 * using an already-resolved $payload. Bypasses the WP hook system so it
	 * works reliably even when called from async contexts (Action Scheduler).
	 */
	public function trigger_event( string $event, array $payload ): void {
		foreach ( Query::get_active_workflows_for_event( $event ) as $trigger ) {
			$this->start_trigger_run( $trigger, $payload );
		}
	}

	/**
	 * Programmatically start a workflow run with custom trigger data, bypassing
	 * the normal WordPress hook system. The workflow must have an active version.
	 *
	 * Returns the Run ID on success, or false if the workflow or its active
	 * version/trigger node could not be found.
	 */
	public function run_workflow( int $workflow_id, array $data = [] ) {
		$workflow = \Zaplane\Models\Workflow::find( $workflow_id );
		if ( ! $workflow ) {
			return false;
		}

		$version = $workflow->activeVersion();
		if ( ! $version ) {
			return false;
		}

		$triggerNode = null;
		foreach ( $version->getGraph()['nodes'] ?? [] as $node ) {
			if ( ( $node['type'] ?? '' ) === 'trigger' ) {
				$triggerNode = $node;
				break;
			}
		}

		if ( ! $triggerNode ) {
			return false;
		}

		$trigger = [
			'workflow_version_id' => $version->id,
			'workflow_id'         => $workflow->id,
			'id'                  => $triggerNode['id'],
			'app'                 => $triggerNode['data']['app'] ?? '',
			'graph_node'          => $triggerNode,
		];

		return $this->start_trigger_run( $trigger, $data );
	}

	public function spawn_node_run( int $run_id, int $node_key, array $input, ?int $parent, ?array $node_meta = null ) {
		$nodeRun = NodeRun::create([
			'run_id'             => $run_id,
			'node_key'           => $node_key,
			'node_meta_json'     => $node_meta,
			'parent_node_run_id' => $parent,
			'status'             => 'pending',
			'input_json'         => $input,
			'started_at'         => current_time( 'mysql' ),
		]);

		self::enqueue_node_run( $nodeRun->id );
	}

	private function extract_node_meta( array $node ): array {
		return [
			'app'   => $node['data']['app'] ?? null,
			'event' => $node['data']['event'] ?? null,
			'label' => $node['data']['label'] ?? null,
		];
	}

	public static function enqueue_node_run( int $node_run_id ): void {
		as_enqueue_async_action(
			'zaplane_execute_node_run',
			[ 'node_run_id' => $node_run_id ],
			'zaplane'
		);
	}

	public function resume_delayed_run( int $run_id, int $node_run_id, int $node_key, array $output ) {
		$run = Run::find( $run_id );
		$nodeRun = NodeRun::find( $node_run_id );

		if ( ! $run || ! $nodeRun || 'delayed' !== $nodeRun->status ) {
			return;
		}

		$graph = $this->load_graph( $run->workflow_version_id );

		$nodeRun->setOutput( $output );
		$nodeRun->status = 'completed';
		$nodeRun->finished_at = current_time( 'mysql' );
		$nodeRun->save();

		$this->spawn_children( $nodeRun, $output, $graph, $run );

		$this->finalize_run( $run_id );
	}

	public function dispatch_node_run( int $node_run_id ) {
		$nodeRun = NodeRun::find( $node_run_id );

		if ( ! $nodeRun || ! $nodeRun->isPending() ) {
			return;
		}

		$nodeRun->markAsRunning();
		$this->execute_node( $nodeRun );
	}

	private function execute_node( NodeRun $nodeRun ) {
		$run = $nodeRun->run();

		if ( ! $run ) {
			$nodeRun->markAsFailed( 'Run not found' );
			return;
		}

		$graph = $this->load_graph( $run->workflow_version_id );
		$node = $this->find_node( $graph, $nodeRun->node_key, $run->id );
		$input = $nodeRun->getInput();

		$app = strtolower( $node['data']['app'] ?? '' );

		$context = $this->buildNodeContext( $run->id, $run, $node );
		$resolveData = $input + $context;

		$node['_run_id'] = $run->id;
		$node['_node_run_id'] = $nodeRun->id;

		// AI Agent sub-nodes: nodes wired into the agent's tool/memory/model
		// handles are configuration providers, not flow steps. Collect them here
		// so the agent can use them; they never execute on their own (nothing in
		// the main flow connects into them).
		if ( 'ai-agent' === $app ) {
			$subs = $this->collect_sub_nodes( $graph, $nodeRun->node_key );

			// Resolve {{...}} in each sub-node's config against the run context, the
			// same way the agent's own config is resolved — otherwise e.g. a Memory
			// node's dynamic conversation_key would stay a literal and never match.
			foreach ( [ 'ai_model', 'ai_memory' ] as $handle ) {
				if ( ! empty( $subs[ $handle ]['data']['config'] ) && is_array( $subs[ $handle ]['data']['config'] ) ) {
					$subs[ $handle ]['data']['config'] = $this->resolveConfigValues( $subs[ $handle ]['data']['config'], $resolveData );
				}
			}
			foreach ( $subs['ai_tool'] as $k => $tool_node ) {
				if ( ! empty( $tool_node['data']['config'] ) && is_array( $tool_node['data']['config'] ) ) {
					$subs['ai_tool'][ $k ]['data']['config'] = $this->resolveConfigValues( $tool_node['data']['config'], $resolveData );
				}
			}

			$node['_sub_nodes'] = $subs;
		}

		try {
			if ( 'trigger' === $node['type'] ) {
				$output = $input;
			} elseif ( in_array( $app, [ 'condition', 'filter' ], true ) ) {
				$integration = $this->container->get( 'integrations' )->get( $app );
				if ( ! $integration ) {
					throw IntegrationException::notFound( $node['data']['app'] );
				}
				$node = $this->inject_credentials( $node );
				$output = $integration::execute_node( $node, $resolveData );
			} else {
				$integration = $this->container->get( 'integrations' )->get( $app );
				if ( ! $integration ) {
					throw IntegrationException::notFound( $node['data']['app'] );
				}
				$node = $this->inject_credentials( $node );
				$node = $this->resolveNodeConfig( $node, $resolveData );
				$output = $integration::execute_node( $node, $input );
			}

			if ( isset( $output['status'] ) && 'delayed' === $output['status'] ) {
				$nodeRun->status = 'delayed';
				$nodeRun->output_json = $output;
				$nodeRun->save();

				$this->finalize_run( $run->id );
				return;
			}

			if ( isset( $output['status'] ) && 'iterate' === $output['status'] ) {
				$nodeRun->setOutput( $output );

				$this->spawn_children( $nodeRun, $output, $graph, $run );

				$remaining = $output['remaining'] ?? [];
				if ( ! empty( $remaining ) ) {
					// `iterator_state` lets a looping tool carry counters (index/total)
					// forward across passes without threading them by hand.
					$iteratorInput = array_merge(
						$input,
						[
							'_is_iterating' => true,
							'_remaining'    => $remaining,
						],
						is_array( $output['iterator_state'] ?? null ) ? $output['iterator_state'] : []
					);

					$this->spawn_node_run(
						$run->id,
						$nodeRun->node_key,
						$iteratorInput,
						$nodeRun->parent_node_run_id,
						$nodeRun->node_meta_json
					);
				}

				$this->finalize_run( $run->id );
				return;
			}//end if

			$nodeRun->setOutput( $output );

			$this->spawn_children( $nodeRun, $output, $graph, $run );
		} catch ( \Throwable $e ) {
			$error_data = [
				'error' => $e->getMessage(),
				'error_code' => method_exists( $e, 'getErrorCode' ) ? $e->getErrorCode() : 'unknown',
			];

			if ( method_exists( $e, 'getContext' ) ) {
				$error_data['context'] = $e->getContext();
			}

			$nodeRun->output_json = $error_data;
			$nodeRun->status = 'failed';
			$nodeRun->finished_at = current_time( 'mysql' );
			$nodeRun->save();
		}//end try

		$this->finalize_run( $run->id );
	}

	private function spawn_children( NodeRun $nodeRun, array $output, array $graph, Run $run ) {
		if ( $run->target_node_key && $nodeRun->node_key === $run->target_node_key ) {
			return;
		}

		$pass = $output['pass'] ?? $output['data']['pass'] ?? null;
		if ( null !== $pass ) {
			if ( false === $pass ) {
				return;
			}
			$output = $output['data'] ?? $output;
		}

		$port = $output['port'] ?? null;
		$childInput = $port ? ( $output['data'] ?? $output ) : $output;

		$graphNodeMap = [];
		foreach ( $graph['nodes'] ?? [] as $n ) {
			$graphNodeMap[ (int) $n['id'] ] = $n;
		}

		foreach ( $graph['edges'] as $edge ) {
			if ( (int) $edge['source'] !== $nodeRun->node_key ) {
				continue;
			}

			if ( null !== $port ) {
				$edgeHandle = $edge['sourceHandle'] ?? null;
				if ( null !== $edgeHandle && $edgeHandle !== $port ) {
					continue;
				}
			}

			$targetKey  = (int) $edge['target'];
			$targetNode = $graphNodeMap[ $targetKey ] ?? null;

			$this->spawn_node_run(
				$nodeRun->run_id,
				$targetKey,
				$childInput,
				$nodeRun->id,
				$targetNode ? $this->extract_node_meta( $targetNode ) : null
			);
		}//end foreach
	}

	private function finalize_run( int $run_id ) {
		$pendingCount = NodeRun::where( 'run_id', $run_id )
			->whereIn( 'status', [ 'pending', 'running' ] )
			->fresh()
			->count();

		if ( 0 === $pendingCount ) {
			$delayedCount = NodeRun::where( 'run_id', $run_id )
				->where( 'status', 'delayed' )
				->fresh()
				->count();

			if ( $delayedCount > 0 ) {
				return;
			}

			$failedCount = NodeRun::where( 'run_id', $run_id )
				->where( 'status', 'failed' )
				->fresh()
				->count();

			$run = Run::find( $run_id );
			if ( $run ) {
				$run->status = $failedCount ? 'failed' : 'completed';
				$run->finished_at = current_time( 'mysql' );
				$run->save();
			}
		}//end if
	}



	private function buildNodeContext( int $run_id, ?Run $run = null, array $node = [] ): array {
		$nodeRuns = NodeRun::where( 'run_id', $run_id )
			->where( 'status', 'completed' )
			->orderBy( 'id', 'asc' )
			->fresh()
			->get();

		$context = [];
		foreach ( $nodeRuns as $nr ) {
			$output = $nr->getOutput();

			if ( is_array( $output ) && isset( $output['port'], $output['data'] ) ) {
				$output = $output['data'];
			}

			$context[ (string) $nr->node_key ] = is_array( $output ) ? $output : [ 'value' => $output ];
		}

		// Only inject global context groups that this node's config actually references.
		// e.g. if config has {{wp.user_email}} we build the 'wp' group; if not, we skip it.
		$prefixes = GlobalContext::detect_prefixes( $node['data']['config'] ?? [] );
		if ( $prefixes ) {
			$context = array_merge( $context, GlobalContext::build( $prefixes, $run ) );
		}

		return $context;
	}

	/**
	 * Gather the sub-nodes wired into a node's AI-agent handles (ai_tool,
	 * ai_memory, ai_model). Returns tool providers as a list and memory/model as
	 * single nodes. These are inspected by the agent — never executed as steps.
	 *
	 * @return array{ai_tool:array<int,array<string,mixed>>,ai_memory:?array<string,mixed>,ai_model:?array<string,mixed>}
	 */
	private function collect_sub_nodes( array $graph, int $node_key ): array {
		$subs = [
			'ai_tool' => [],
			'ai_memory' => null,
			'ai_model' => null
		];

		$nodeMap = [];
		foreach ( $graph['nodes'] ?? [] as $n ) {
			$nodeMap[ (int) $n['id'] ] = $n;
		}

		foreach ( $graph['edges'] ?? [] as $edge ) {
			if ( (int) ( $edge['target'] ?? 0 ) !== $node_key ) {
				continue;
			}
			$handle = $edge['targetHandle'] ?? '';
			if ( ! array_key_exists( $handle, $subs ) ) {
				continue;
			}
			$src = $nodeMap[ (int) ( $edge['source'] ?? 0 ) ] ?? null;
			if ( ! $src ) {
				continue;
			}
			if ( 'ai_tool' === $handle ) {
				$subs['ai_tool'][] = $src;
			} else {
				$subs[ $handle ] = $src;
			}
		}

		return $subs;
	}

	private function inject_credentials( array $node ): array {
		$connection_id = $node['data']['connection_id'] ?? null;

		if ( ! $connection_id ) {
			return $node;
		}

		try {
			$manager = $this->container->get( 'connections' );
			$node['_connection_credentials'] = $manager->get_execution_credentials( (int) $connection_id );
		} catch ( \Throwable $e ) {
			$e->getMessage();
		}

		return $node;
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

	private function load_graph( int $versionId ): array {
		$version = WorkflowVersion::find( $versionId );
		return $version ? $version->getGraph() : [
			'nodes' => [],
			'edges' => []
		];
	}

	private function find_node( array $graph, int $key, int $run_id = 0 ): array {
		foreach ( $graph['nodes'] as $node ) {
			if ( (int) $node['id'] === $key ) {
				return $node;
			}
		}
		throw WorkflowException::nodeNotFound( esc_html( (string) $run_id ), esc_html( (string) $key ) );
	}
}
