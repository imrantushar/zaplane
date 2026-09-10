<?php

namespace Zaplane\Mcp;

use Zaplane\Authoring\Catalog;
use Zaplane\Authoring\GraphTester;
use Zaplane\Authoring\GraphValidator;
use Zaplane\Authoring\WorkflowAuthor;
use Zaplane\Models\Connection;
use Zaplane\Models\NodeRun;
use Zaplane\Models\Recipe;
use Zaplane\Models\Run;
use Zaplane\Models\Workflow;
use Zaplane\Services\BlueprintService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The tools the MCP endpoint exposes, and the dispatch behind them.
 *
 * Split out of the controller so the tool surface can be tested without a
 * request, and so the same handlers can back a second caller later.
 *
 * Every tool declares the scope its token must hold. Reading and authoring are
 * separate from running: authoring a workflow is reversible and inspectable,
 * while running one sends the mail and takes the payment, so `run` is never
 * granted by default.
 */
class ToolRegistry {

	/** Cap on rows any single listing returns. */
	private const MAX_LIMIT = 100;

	/**
	 * name => [scope, handler, description, schema properties, required].
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function definitions(): array {
		$s = fn( string $desc ) => [
			'type'        => 'string',
			'description' => $desc,
		];
		$i = fn( string $desc ) => [
			'type'        => 'integer',
			'description' => $desc,
		];

		return [
			/* ---------------------------- discovery --------------------------- */
			'search_capabilities' => [
				'scope'       => TokenStore::SCOPE_READ,
				'description' => 'Find the triggers and actions that could do something, by describing it in plain words. Start here — there are hundreds of capabilities across all apps, and this narrows them to a shortlist. Then call describe_app for the exact field schema.',
				'properties'  => [
					'query' => $s( 'What you want to happen, e.g. "when a subscription payment fails" or "send a Slack message".' ),
					'type'  => $s( 'Restrict to "trigger" or "action". Omit for both.' ),
					'limit' => $i( 'Max results (default 20).' ),
				],
				'required'    => [ 'query' ],
			],
			'list_apps' => [
				'scope'       => TokenStore::SCOPE_READ,
				'description' => 'List every connected app and flow-control tool with how many triggers and actions each has. Identity only — call describe_app for field schemas.',
				'properties'  => [
					'category' => $s( 'Filter to "app" or "tool". Omit for both.' ),
				],
				'required'    => [],
			],
			'list_field_options' => [
				'scope'       => TokenStore::SCOPE_READ,
				'description' => 'Resolve the allowed values for a config field whose options live on this site — a course, a product, a form, a Slack channel, a CRM list. describe_app marks such a field with "dynamic" and gives it no options list, because only the site knows them. Roughly a third of all capabilities have a required field like this, and guessing an id produces a workflow that saves and then never matches anything, so call this for every dynamic field before writing the node.',
				'properties'  => [
					'app'    => $s( 'App or tool slug.' ),
					'event'  => $s( 'The trigger or action key the field belongs to.' ),
					'field'  => $s( 'The field key, e.g. course_id.' ),
					'config' => [
						'type'        => 'object',
						'description' => 'The config decided so far. Some lists depend on an earlier choice — a form\'s fields need its form_id — so pass what you already have.',
					],
				],
				'required'    => [ 'app', 'event', 'field' ],
			],
			'describe_app' => [
				'scope'       => TokenStore::SCOPE_READ,
				'description' => 'Full detail for one app: every trigger and action it exposes, each with the exact config fields, types, required flags and allowed option values. This is what you need to write a valid workflow node. A field carrying "dynamic" has no fixed options — call list_field_options for it.',
				'properties'  => [
					'slug' => $s( 'App or tool slug, from list_apps or search_capabilities.' ),
				],
				'required'    => [ 'slug' ],
			],

			/* ---------------------------- authoring --------------------------- */
			'validate_graph' => [
				'scope'       => TokenStore::SCOPE_READ,
				'description' => 'Check a workflow graph against the app catalogue without saving it. Reports unknown apps or events, missing required fields, bad option values and broken edges. Use this before create_workflow to see errors early.',
				'properties'  => [
					'graph' => [
						'type'        => 'object',
						'description' => 'A graph of { nodes: [...], edges: [...] }.',
					],
				],
				'required'    => [ 'graph' ],
			],
			'test_workflow' => [
				'scope'       => TokenStore::SCOPE_READ,
				'description' => 'Dry-run a workflow: walk it in run order, resolve every {{...}} against sample data, and report what each field would actually contain — without executing anything. validate_graph proves the apps and fields are real; this proves the wiring carries data, catching the typo in {{2.post_title}} that otherwise only surfaces once the workflow is live and the mail has gone out. Pass workflow_id for a saved workflow or graph for an unsaved draft. Nothing is sent, charged or written.',
				'properties'  => [
					'workflow_id'  => $i( 'Saved workflow to test. Give this or graph, not both.' ),
					'graph'        => [
						'type'        => 'object',
						'description' => 'An unsaved graph of { nodes: [...], edges: [...] }, to test before creating it.',
					],
					'trigger_data' => [
						'type'        => 'object',
						'description' => 'Realistic trigger output to resolve against, replacing the integration\'s declared sample. Use it to check the copy with a real name and course title.',
					],
				],
				'required'    => [],
			],
			'create_workflow' => [
				'scope'       => TokenStore::SCOPE_WRITE,
				'description' => 'Create a workflow from a graph. Node ids, canvas positions, labels, icons, trigger hooks and straight-line edges are filled in for you, so each node only needs { type, data: { app, event, config } }. Node ids, if you supply them, must be numeric strings ("1", "2"). Created as a draft; apps needing a connection are left unlinked for the site owner to pick.',
				'properties'  => [
					'title'     => $s( 'Name for the new workflow.' ),
					'graph'     => [
						'type'        => 'object',
						'description' => 'A graph of { nodes: [...], edges: [...] }. Omit edges to chain the nodes in order.',
					],
					'folder_id' => $i( 'Optional folder to file it under.' ),
				],
				'required'    => [ 'title', 'graph' ],
			],
			'update_workflow' => [
				'scope'       => TokenStore::SCOPE_WRITE,
				'description' => 'Replace an existing workflow\'s graph. A draft is edited in place; a live workflow keeps its version history.',
				'properties'  => [
					'workflow_id' => $i( 'Workflow to update.' ),
					'graph'       => [
						'type'        => 'object',
						'description' => 'The replacement graph.',
					],
				],
				'required'    => [ 'workflow_id', 'graph' ],
			],
			'set_workflow_status' => [
				'scope'       => TokenStore::SCOPE_WRITE,
				'description' => 'Set a workflow to active, paused or draft. Going active re-validates the graph first and refuses if it would not run.',
				'properties'  => [
					'workflow_id' => $i( 'Workflow to change.' ),
					'status'      => $s( 'One of: active, paused, draft.' ),
				],
				'required'    => [ 'workflow_id', 'status' ],
			],
			'create_workflow_from_recipe' => [
				'scope'       => TokenStore::SCOPE_WRITE,
				'description' => 'Create a workflow from a ready-made recipe. Any connections it uses must be linked afterwards in the editor.',
				'properties'  => [
					'recipe_id' => $i( 'Recipe to instantiate.' ),
					'title'     => $s( 'Optional title for the new workflow.' ),
				],
				'required'    => [ 'recipe_id' ],
			],

			/* ---------------------------- inspection -------------------------- */
			'list_workflows' => [
				'scope'       => TokenStore::SCOPE_READ,
				'description' => 'List workflows with their status.',
				'properties'  => [
					'status' => $s( 'Filter by active, paused or draft.' ),
					'limit'  => $i( 'Max rows (default 50).' ),
				],
				'required'    => [],
			],
			'get_workflow' => [
				'scope'       => TokenStore::SCOPE_READ,
				'description' => 'One workflow with its active graph, so you can read what it does before changing it.',
				'properties'  => [
					'workflow_id' => $i( 'Workflow to fetch.' ),
				],
				'required'    => [ 'workflow_id' ],
			],
			'list_recipes' => [
				'scope'       => TokenStore::SCOPE_READ,
				'description' => 'List the ready-made recipe templates available on this site.',
				'properties'  => [],
				'required'    => [],
			],
			'list_runs' => [
				'scope'       => TokenStore::SCOPE_READ,
				'description' => 'Recent workflow runs and their status. Filter by workflow or by status to find failures.',
				'properties'  => [
					'workflow_id' => $i( 'Only runs of this workflow.' ),
					'status'      => $s( 'Filter by running, completed or failed.' ),
					'limit'       => $i( 'Max rows (default 20).' ),
				],
				'required'    => [],
			],
			'get_run' => [
				'scope'       => TokenStore::SCOPE_READ,
				'description' => 'One run in detail, including each node that executed, its status and any error — use this to explain why a workflow failed.',
				'properties'  => [
					'run_id' => $i( 'Run to inspect.' ),
				],
				'required'    => [ 'run_id' ],
			],
			'list_connections' => [
				'scope'       => TokenStore::SCOPE_READ,
				'description' => 'List the saved app connections and their ids, so a workflow node can be pointed at one. Never returns credentials.',
				'properties'  => [
					'app' => $s( 'Filter to one app slug.' ),
				],
				'required'    => [],
			],

			/* ---------------------------- knowledge --------------------------- */
			'search_knowledge' => [
				'scope'       => TokenStore::SCOPE_READ,
				'description' => 'Search a business knowledge base (products, prices, policies) for relevant entries.',
				'properties'  => [
					'business_key' => $s( 'Business key, e.g. default.' ),
					'query'        => $s( 'What to search for.' ),
					'limit'        => $i( 'Max results (default 5).' ),
				],
				'required'    => [ 'business_key', 'query' ],
			],
			'list_businesses' => [
				'scope'       => TokenStore::SCOPE_READ,
				'description' => 'List the business keys that have knowledge entries, with entry counts.',
				'properties'  => [],
				'required'    => [],
			],
			'sync_content' => [
				'scope'       => TokenStore::SCOPE_WRITE,
				'description' => 'Sync a WordPress post type (posts, pages, products, any CPT) into a business knowledge base. Each item becomes one entry.',
				'properties'  => [
					'business_key' => $s( 'Business key to sync into.' ),
					'post_type'    => $s( 'Post type slug, e.g. post, page, product.' ),
					'post_status'  => $s( 'Status to include (default publish).' ),
					'meta_keys'    => $s( 'Optional comma-separated custom field keys to append.' ),
					'taxonomies'   => $s( 'Optional comma-separated taxonomies whose terms to append.' ),
					'limit'        => $i( 'Max items (0 = all).' ),
					'prune'        => [
						'type'        => 'boolean',
						'description' => 'Remove entries whose source item is gone. Default true.',
					],
				],
				'required'    => [ 'business_key', 'post_type' ],
			],

			/* ------------------------------- run ------------------------------ */
			'run_workflow' => [
				'scope'       => TokenStore::SCOPE_RUN,
				'description' => 'Run a workflow now. This performs its real side effects — sending mail, charging, posting to other services. Requires a token with the "run" scope.',
				'properties'  => [
					'workflow_id' => $i( 'Workflow to run.' ),
					'data'        => [
						'type'        => 'object',
						'description' => 'Optional trigger data passed to the workflow.',
					],
				],
				'required'    => [ 'workflow_id' ],
			],
		];
	}

	/**
	 * MCP tools/list payload, filtered to what this token may call.
	 *
	 * @param array<int,string> $scopes
	 * @return array<int,array<string,mixed>>
	 */
	public static function schemas( array $scopes ): array {
		$tools = [];

		foreach ( self::definitions() as $name => $def ) {
			if ( ! in_array( $def['scope'], $scopes, true ) ) {
				continue;
			}

			$tools[] = [
				'name'        => $name,
				'description' => $def['description'],
				'inputSchema' => [
					'type'       => 'object',
					'properties' => (object) $def['properties'],
					'required'   => $def['required'],
				],
			];
		}

		return $tools;
	}

	public static function scope_for( string $name ): ?string {
		$defs = self::definitions();
		return isset( $defs[ $name ] ) ? (string) $defs[ $name ]['scope'] : null;
	}

	/**
	 * Run one tool. Throws on anything the caller should see as an error.
	 *
	 * @param array<string,mixed> $args
	 * @param array<string,mixed> $token The resolved token record, for attribution.
	 * @return array<string,mixed>
	 * @throws \RuntimeException|\InvalidArgumentException
	 */
	public static function call( string $name, array $args, array $token = [] ): array {
		switch ( $name ) {
			case 'search_capabilities':
				return self::search_capabilities( $args );
			case 'list_apps':
			case 'list_integrations': // Pre-scoping name, still answered.
				return [ 'apps' => Catalog::list_apps( (string) ( $args['category'] ?? '' ) ) ];
			case 'describe_app':
				return self::describe_app( $args );
			case 'list_field_options':
				return self::list_field_options( $args );
			case 'validate_graph':
				return self::validate_graph( $args );
			case 'test_workflow':
				return self::test_workflow( $args );
			case 'create_workflow':
				return self::create_workflow( $args, $token );
			case 'update_workflow':
				return self::update_workflow( $args );
			case 'set_workflow_status':
				return WorkflowAuthor::set_status(
					(int) ( $args['workflow_id'] ?? 0 ),
					(string) ( $args['status'] ?? '' )
				);
			case 'create_workflow_from_recipe':
				return self::create_from_recipe( $args );
			case 'list_workflows':
				return self::list_workflows( $args );
			case 'get_workflow':
				return self::get_workflow( $args );
			case 'list_recipes':
				return self::list_recipes();
			case 'list_runs':
				return self::list_runs( $args );
			case 'get_run':
				return self::get_run( $args );
			case 'list_connections':
				return self::list_connections( $args );
			case 'search_knowledge':
				return self::search_knowledge( $args );
			case 'list_businesses':
				return self::list_businesses();
			case 'sync_content':
				return self::sync_content( $args );
			case 'run_workflow':
				return self::run_workflow( $args );
		}

		throw new \InvalidArgumentException( 'Unknown tool: ' . $name );
	}

	/* ------------------------------ handlers ------------------------------ */

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function search_capabilities( array $args ): array {
		$query = trim( (string) ( $args['query'] ?? '' ) );
		if ( '' === $query ) {
			throw new \InvalidArgumentException( 'query is required.' );
		}

		$type = (string) ( $args['type'] ?? '' );
		if ( ! in_array( $type, [ '', 'trigger', 'action' ], true ) ) {
			throw new \InvalidArgumentException( 'type must be "trigger", "action", or omitted.' );
		}

		$matches = Catalog::search( $query, $type, self::limit( $args, 20 ) );

		return [
			'matches' => $matches,
			'hint'    => empty( $matches )
				? 'Nothing matched. Try fewer, plainer words, or call list_apps to browse.'
				: 'Call describe_app with one of these app slugs for its exact config fields.',
		];
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function describe_app( array $args ): array {
		$slug = trim( (string) ( $args['slug'] ?? '' ) );
		$app  = '' === $slug ? null : Catalog::describe_app( $slug );

		if ( null === $app ) {
			throw new \InvalidArgumentException( 'Unknown app "' . $slug . '". Use list_apps or search_capabilities to find valid slugs.' );
		}

		return $app;
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function list_field_options( array $args ): array {
		$app   = trim( (string) ( $args['app'] ?? '' ) );
		$event = trim( (string) ( $args['event'] ?? '' ) );
		$key   = trim( (string) ( $args['field'] ?? '' ) );

		if ( '' === $app || '' === $event || '' === $key ) {
			throw new \InvalidArgumentException( 'app, event and field are all required.' );
		}

		$config = ( isset( $args['config'] ) && is_array( $args['config'] ) ) ? $args['config'] : [];
		$result = Catalog::field_options( $app, $event, $key, $config );

		if ( null === $result ) {
			$capability = Catalog::find_capability( $app, $event );
			if ( null === $capability ) {
				throw new \InvalidArgumentException(
					sprintf( '"%s" is not a trigger or action of app "%s".', $event, $app )
				);
			}
			throw new \InvalidArgumentException(
				sprintf(
					'Field "%s" is not in %s/%s. Fields: %s',
					$key,
					$app,
					$event,
					implode( ', ', array_column( (array) $capability['schema'], 'key' ) )
				)
			);
		}

		if ( ! $result['resolved'] ) {
			throw new \RuntimeException( (string) $result['error'] );
		}

		if ( ! $result['dynamic'] ) {
			return [
				'app'     => $app,
				'event'   => $event,
				'field'   => $key,
				'dynamic' => false,
				'options' => $result['options'],
				'hint'    => empty( $result['options'] )
					? 'This field takes a free value; there is no list to choose from.'
					: 'These options ship with the app, so describe_app already returned them.',
			];
		}

		return [
			'app'     => $app,
			'event'   => $event,
			'field'   => $key,
			'dynamic' => true,
			'options' => $result['options'],
			'hint'    => $result['options']
				? 'Use one of these "value"s verbatim in the node config.'
				: 'Nothing to choose from — this site has none yet.',
		];
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function validate_graph( array $args ): array {
		$graph = $args['graph'] ?? null;
		if ( ! is_array( $graph ) ) {
			throw new \InvalidArgumentException( 'graph must be an object with "nodes" and "edges".' );
		}

		$normalized = WorkflowAuthor::normalize( $graph );
		$report     = GraphValidator::check( $normalized );

		return [
			'valid'            => $report['valid'],
			'errors'           => $report['errors'],
			'warnings'         => $report['warnings'],
			'normalized_graph' => $normalized,
		];
	}

	/**
	 * Dry-run a saved or unsaved graph. Resolves only — see GraphTester.
	 *
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function test_workflow( array $args ): array {
		$id    = (int) ( $args['workflow_id'] ?? 0 );
		$graph = $args['graph'] ?? null;

		if ( $id && is_array( $graph ) ) {
			throw new \InvalidArgumentException( 'Give workflow_id or graph, not both.' );
		}

		$title = null;

		if ( $id ) {
			$workflow = Workflow::find( $id );
			if ( ! $workflow ) {
				throw new \InvalidArgumentException( 'Workflow ' . $id . ' not found.' );
			}

			$version = $workflow->activeVersion();
			$graph   = $version ? $version->getGraph() : null;
			$title   = $workflow->title;

			if ( ! is_array( $graph ) ) {
				throw new \RuntimeException( 'Workflow ' . $id . ' has no saved graph to test.' );
			}
		}

		if ( ! is_array( $graph ) ) {
			throw new \InvalidArgumentException( 'Pass workflow_id for a saved workflow, or graph for one you have not created yet.' );
		}

		$report = GraphTester::test( $graph, (array) ( $args['trigger_data'] ?? [] ) );

		if ( $id ) {
			$report = [
				'workflow_id' => $id,
				'title'       => $title,
			] + $report;
		}

		return $report;
	}

	/**
	 * @param array<string,mixed> $args
	 * @param array<string,mixed> $token
	 * @return array<string,mixed>
	 */
	private static function create_workflow( array $args, array $token ): array {
		$graph = $args['graph'] ?? null;
		if ( ! is_array( $graph ) ) {
			throw new \InvalidArgumentException( 'graph must be an object with "nodes" and "edges".' );
		}

		return WorkflowAuthor::create(
			(string) ( $args['title'] ?? '' ),
			$graph,
			[
				// Bearer auth means there is no current WordPress user; without this
				// the workflow would be saved ownerless.
				'user_id'   => (int) ( $token['user_id'] ?? 0 ),
				'folder_id' => isset( $args['folder_id'] ) ? (int) $args['folder_id'] : null,
			]
		);
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function update_workflow( array $args ): array {
		$graph = $args['graph'] ?? null;
		if ( ! is_array( $graph ) ) {
			throw new \InvalidArgumentException( 'graph must be an object with "nodes" and "edges".' );
		}

		return WorkflowAuthor::save( (int) ( $args['workflow_id'] ?? 0 ), $graph );
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function create_from_recipe( array $args ): array {
		$recipe_id = (int) ( $args['recipe_id'] ?? 0 );
		$recipe    = $recipe_id ? Recipe::find( $recipe_id ) : null;

		if ( ! $recipe ) {
			throw new \InvalidArgumentException( 'Recipe ' . $recipe_id . ' not found.' );
		}

		$blueprint = $recipe->getBlueprint();
		if ( empty( $blueprint ) ) {
			throw new \RuntimeException( 'Recipe blueprint is empty.' );
		}

		$workflow = ( new BlueprintService() )->import(
			$blueprint,
			sanitize_text_field( (string) ( $args['title'] ?? '' ) )
		);

		return [
			'workflow_id'           => (int) $workflow->id,
			'title'                 => $workflow->title,
			'status'                => $workflow->status,
			'connections_to_relink' => $blueprint['connections'] ?? [],
		];
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function list_workflows( array $args ): array {
		$query = Workflow::orderBy( 'id', 'desc' );

		$status = (string) ( $args['status'] ?? '' );
		if ( in_array( $status, [ 'active', 'paused', 'draft' ], true ) ) {
			$query = $query->where( 'status', $status );
		}

		$rows = $query->limit( self::limit( $args, 50 ) )->get();

		$out = [];
		foreach ( $rows as $workflow ) {
			$out[] = [
				'id'     => (int) $workflow->id,
				'title'  => $workflow->title,
				'status' => $workflow->status,
			];
		}

		return [ 'workflows' => $out ];
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function get_workflow( array $args ): array {
		$id       = (int) ( $args['workflow_id'] ?? 0 );
		$workflow = $id ? Workflow::find( $id ) : null;

		if ( ! $workflow ) {
			throw new \InvalidArgumentException( 'Workflow ' . $id . ' not found.' );
		}

		$version = $workflow->activeVersion();

		return [
			'id'             => (int) $workflow->id,
			'title'          => $workflow->title,
			'status'         => $workflow->status,
			'version_id'     => $version ? (int) $version->id : null,
			'version_number' => $version ? $version->version_number : null,
			'graph'          => $version ? $version->getGraph() : null,
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function list_recipes(): array {
		$out = [];

		foreach ( Recipe::orderBy( 'id', 'asc' )->limit( self::MAX_LIMIT )->get() as $recipe ) {
			$out[] = [
				'id'          => (int) $recipe->id,
				'title'       => $recipe->title,
				'description' => $recipe->description ?? '',
			];
		}

		return [ 'recipes' => $out ];
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function list_runs( array $args ): array {
		$query = Run::orderBy( 'id', 'desc' );

		if ( ! empty( $args['workflow_id'] ) ) {
			$query = $query->where( 'workflow_id', (int) $args['workflow_id'] );
		}

		$status = (string) ( $args['status'] ?? '' );
		if ( '' !== $status ) {
			$query = $query->where( 'status', $status );
		}

		$out = [];
		foreach ( $query->limit( self::limit( $args, 20 ) )->get() as $run ) {
			$out[] = [
				'id'          => (int) $run->id,
				'workflow_id' => (int) $run->workflow_id,
				'status'      => $run->status,
				'started_at'  => $run->started_at,
				'finished_at' => $run->finished_at,
				'last_error'  => $run->last_error,
			];
		}

		return [ 'runs' => $out ];
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function get_run( array $args ): array {
		$id  = (int) ( $args['run_id'] ?? 0 );
		$run = $id ? Run::find( $id ) : null;

		if ( ! $run ) {
			throw new \InvalidArgumentException( 'Run ' . $id . ' not found.' );
		}

		$steps = [];
		foreach ( NodeRun::where( 'run_id', $id )->orderBy( 'id', 'asc' )->get() as $nodeRun ) {
			$output = $nodeRun->getOutput();

			$steps[] = [
				'node_key'    => (int) $nodeRun->node_key,
				'status'      => $nodeRun->status,
				'attempts'    => (int) $nodeRun->attempts,
				'started_at'  => $nodeRun->started_at,
				'finished_at' => $nodeRun->finished_at,
				// The whole payload can be large; an error is what a caller
				// diagnosing a failure actually needs.
				'error'       => is_array( $output ) ? ( $output['error'] ?? null ) : null,
			];
		}

		return [
			'id'          => (int) $run->id,
			'workflow_id' => (int) $run->workflow_id,
			'status'      => $run->status,
			'started_at'  => $run->started_at,
			'finished_at' => $run->finished_at,
			'last_error'  => $run->last_error,
			'steps'       => $steps,
		];
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function list_connections( array $args ): array {
		$query = Connection::orderBy( 'id', 'desc' );

		$app = (string) ( $args['app'] ?? '' );
		if ( '' !== $app ) {
			$query = $query->where( 'app', $app );
		}

		$out = [];
		foreach ( $query->limit( self::MAX_LIMIT )->get() as $connection ) {
			// Never the credentials — only what a node needs to reference one.
			$out[] = [
				'id'               => (int) $connection->id,
				'app'              => $connection->app,
				'name'             => $connection->name,
				'auth_type'        => $connection->auth_type,
				'status'           => $connection->status,
				'last_test_status' => $connection->last_test_status,
			];
		}

		return [ 'connections' => $out ];
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function search_knowledge( array $args ): array {
		if ( ! class_exists( '\Zaplane\Integrations\Knowledge' ) ) {
			throw new \RuntimeException( 'Knowledge integration unavailable.' );
		}

		$res = \Zaplane\Integrations\Knowledge::execute_node(
			[
				'data' => [
					'event'  => 'retrieve',
					'config' => [
						'business_key' => (string) ( $args['business_key'] ?? '' ),
						'query'        => (string) ( $args['query'] ?? '' ),
						'limit'        => (int) ( $args['limit'] ?? 5 ),
					],
				],
			],
			[]
		);

		$data = $res['data'] ?? [];

		return [
			'context' => $data['context'] ?? '',
			'matches' => $data['matches'] ?? [],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function list_businesses(): array {
		global $wpdb;

		$table = \Zaplane\Models\Knowledge::getTable();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT business_key, COUNT(*) AS entries FROM {$table} GROUP BY business_key ORDER BY business_key ASC", ARRAY_A );

		return [ 'businesses' => is_array( $rows ) ? $rows : [] ];
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function sync_content( array $args ): array {
		if ( ! class_exists( '\Zaplane\Integrations\Knowledge' ) ) {
			throw new \RuntimeException( 'Knowledge integration unavailable.' );
		}

		$business = trim( (string) ( $args['business_key'] ?? '' ) );
		if ( '' === $business ) {
			throw new \InvalidArgumentException( 'business_key is required.' );
		}

		$res = \Zaplane\Integrations\Knowledge::action_sync_content(
			$business,
			[
				'post_type'   => (string) ( $args['post_type'] ?? '' ),
				'post_status' => (string) ( $args['post_status'] ?? 'publish' ),
				'meta_keys'   => (string) ( $args['meta_keys'] ?? '' ),
				'taxonomies'  => (string) ( $args['taxonomies'] ?? '' ),
				'limit'       => (int) ( $args['limit'] ?? 0 ),
				'prune'       => ( isset( $args['prune'] ) && false === $args['prune'] ) ? 'no' : 'yes',
			],
			[]
		);

		$data = $res['data'] ?? [];

		if ( empty( $data['success'] ) ) {
			throw new \RuntimeException( (string) ( $data['error'] ?? 'Sync failed.' ) );
		}

		return [
			'success'   => true,
			'synced'    => (int) ( $data['synced'] ?? 0 ),
			'pruned'    => (int) ( $data['pruned'] ?? 0 ),
			'post_type' => $data['post_type'] ?? '',
		];
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	private static function run_workflow( array $args ): array {
		$id = (int) ( $args['workflow_id'] ?? 0 );
		if ( ! $id ) {
			throw new \InvalidArgumentException( 'workflow_id is required.' );
		}

		if ( ! function_exists( 'zaplane_run_workflow' ) ) {
			throw new \RuntimeException( 'Run engine unavailable.' );
		}

		$data   = ( isset( $args['data'] ) && is_array( $args['data'] ) ) ? $args['data'] : [];
		$run_id = zaplane_run_workflow( $id, $data );

		if ( ! $run_id ) {
			throw new \RuntimeException( 'Could not start workflow ' . $id . ' — it does not exist, or has no active version with a trigger.' );
		}

		return [
			'started'     => true,
			'run_id'      => (int) $run_id,
			'workflow_id' => $id,
		];
	}

	/**
	 * @param array<string,mixed> $args
	 */
	private static function limit( array $args, int $default ): int {
		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : $default;
		return max( 1, min( self::MAX_LIMIT, $limit ) );
	}
}
