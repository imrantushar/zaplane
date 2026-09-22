<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use Zaplane\Database\Seeders\RecipeSeeding;
use Zaplane\Framework\Classes\Container;
use Zaplane\Models\Recipe;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;
use Zaplane\Recipes\Registry;
use Zaplane\Services\BlueprintService;
use Zaplane\Services\RecipeGroupService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RecipeController extends WP_REST_Controller {

	protected ?Container $container;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
		$this->namespace = 'zaplane/v1';
	}

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/recipes', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'page'     => [
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					],
					'per_page' => [
						'type'              => 'integer',
						'default'           => 20,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					],
					'type'     => [
						'type'    => 'string',
						'enum'    => [ '', Recipe::TYPE_WORKFLOW, Recipe::TYPE_GROUP ],
						'default' => '',
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/recipes/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/workflows/(?P<id>\d+)/to-recipe', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'workflow_to_recipe' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'title'        => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'description'  => [
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					],
					'thumbnail_id' => [
						'type'     => 'integer',
						'required' => false,
						'default'  => null,
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/recipes/(?P<id>\d+)/to-workflow', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'recipe_to_workflow' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'title' => [
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
		] );

		// A recipe's setup: GET describes what it asks, POST creates the workflows.
		register_rest_route( $this->namespace, '/recipes/(?P<id>\d+)/setup', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_setup' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'run_setup' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_items( $request ) {
		// A recipe a plugin registered, or one that changed, shows up without an update.
		Registry::instance()->sync();

		$page    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );
		$type    = (string) ( $request->get_param( 'type' ) ?? '' );
		$byType  = in_array( $type, [ Recipe::TYPE_WORKFLOW, Recipe::TYPE_GROUP ], true );

		$total   = $byType ? Recipe::where( 'type', $type )->count() : Recipe::count();
		$query   = $byType ? Recipe::where( 'type', $type )->orderBy( 'title', 'asc' ) : Recipe::orderBy( 'title', 'asc' );
		$recipes = $query
			->forPage( $page, $perPage )
			->get()
			->map( fn( $r ) => $r->toResponse() )
			->toArray();

		return rest_ensure_response( [
			'data'       => $recipes,
			'pagination' => [
				'page'        => $page,
				'per_page'    => $perPage,
				'total'       => $total,
				'total_pages' => (int) ceil( $total / $perPage ),
			],
		] );
	}

	public function get_item( $request ) {
		Registry::instance()->sync();

		$recipe = Recipe::find( (int) $request['id'] );
		if ( ! $recipe ) {
			return new WP_Error( 'not_found', 'Recipe not found.', [ 'status' => 404 ] );
		}

		return rest_ensure_response( $recipe->toResponse() );
	}

	public function update_item( $request ) {
		$recipe = Recipe::find( (int) $request['id'] );
		if ( ! $recipe ) {
			return new WP_Error( 'not_found', 'Recipe not found.', [ 'status' => 404 ] );
		}

		$params = $request->get_json_params() ?? [];

		if ( isset( $params['title'] ) ) {
			$recipe->title = sanitize_text_field( $params['title'] );
		}

		if ( array_key_exists( 'description', $params ) ) {
			$recipe->description = sanitize_textarea_field( $params['description'] );
		}

		if ( array_key_exists( 'thumbnail_id', $params ) ) {
			$recipe->thumbnail_id = $params['thumbnail_id'] ? (int) $params['thumbnail_id'] : null;
		}

		$recipe->save();

		return rest_ensure_response( $recipe->toResponse() );
	}

	public function delete_item( $request ) {
		$recipe = Recipe::find( (int) $request['id'] );
		if ( ! $recipe ) {
			return new WP_Error( 'not_found', 'Recipe not found.', [ 'status' => 404 ] );
		}

		// A recipe that ships with the plugin would otherwise be seeded again on the next update.
		if ( ! empty( $recipe->slug ) ) {
			RecipeSeeding::dismiss( (string) $recipe->slug );
		}

		$recipe->delete();

		return rest_ensure_response( [
			'deleted' => true,
			'id' => (int) $request['id']
		] );
	}

	public function workflow_to_recipe( $request ) {
		$workflow = Workflow::find( (int) $request['id'] );
		if ( ! $workflow ) {
			return new WP_Error( 'workflow_not_found', 'Workflow not found.', [ 'status' => 404 ] );
		}

		$title       = $request->get_param( 'title' );
		$description = $request->get_param( 'description' ) ?? '';
		$thumbnailId = $request->get_param( 'thumbnail_id' );

		if ( ! $title ) {
			return new WP_Error( 'missing_title', 'Recipe title is required.', [ 'status' => 400 ] );
		}

		$blueprint = ( new BlueprintService() )->serialize( $workflow, 'active' );

		// Derive integration icons from the active version graph nodes
		// (each node carries data.icon). The workflow's cached
		// integration_icons column can be stale/empty, so prefer the graph and
		// fall back to the column only when no node icons are found.
		$icons          = [];
		$active_version = WorkflowVersion::where( 'workflow_id', $workflow->id )
			->where( 'is_active', 1 )
			->first();

		if ( $active_version ) {
			$graph = $active_version->getGraph();
			foreach ( $graph['nodes'] ?? [] as $node ) {
				$icon = $node['data']['icon'] ?? null;
				if ( $icon ) {
					$icons[] = $icon;
				}
			}
		}

		$icons = array_values( array_unique( $icons ) );

		if ( empty( $icons ) ) {
			$icons = $workflow->integration_icons ?? [];
		}

		$recipe = Recipe::create( [
			'title'             => $title,
			'description'       => $description,
			'thumbnail_id'      => $thumbnailId ? (int) $thumbnailId : null,
			'blueprint'         => wp_json_encode( $blueprint ),
			'integration_icons' => wp_json_encode( $icons ),
			'created_by'        => get_current_user_id(),
		] );

		return rest_ensure_response( $recipe->toResponse() );
	}

	public function recipe_to_workflow( $request ) {
		$recipe = Recipe::find( (int) $request['id'] );
		if ( ! $recipe ) {
			return new WP_Error( 'not_found', 'Recipe not found.', [ 'status' => 404 ] );
		}

		if ( $recipe->isGroup() ) {
			return new WP_Error(
				'group_recipe',
				__( 'This recipe sets up several workflows. Use Run Group Recipe to choose them.', 'zaplane' ),
				[ 'status' => 400 ]
			);
		}

		$blueprint = $recipe->getBlueprint();

		if ( empty( $blueprint ) ) {
			return new WP_Error( 'empty_blueprint', 'Recipe blueprint is empty or corrupted.', [ 'status' => 422 ] );
		}

		$titleOverride = sanitize_text_field( $request->get_param( 'title' ) ?? '' );

		// A registered recipe is set up with its defaults, as its setup would.
		if ( isset( $blueprint['workflows'] ) ) {
			try {
				$created = ( new RecipeGroupService() )->install( $recipe, [ 'title' => $titleOverride ] )['workflows'][0];
			} catch ( \InvalidArgumentException $e ) {
				return new WP_Error( 'invalid_setup', $e->getMessage(), [ 'status' => 400 ] );
			} catch ( \Throwable $e ) {
				return new WP_Error( 'import_failed', $e->getMessage(), [ 'status' => 422 ] );
			}

			return rest_ensure_response( [
				'workflow_id'           => $created['id'],
				'title'                 => $created['title'],
				'status'                => $created['status'],
				'connections_to_relink' => [],
			] );
		}

		try {
			$workflow = ( new BlueprintService() )->import( $blueprint, $titleOverride );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'import_failed', $e->getMessage(), [ 'status' => 422 ] );
		}

		return rest_ensure_response( [
			'workflow_id'           => $workflow->id,
			'title'                 => $workflow->title,
			'status'                => $workflow->status,
			'connections_to_relink' => $blueprint['connections'] ?? [],
		] );
	}

	public function get_setup( $request ) {
		$recipe = $this->recipe( (int) $request['id'] );
		if ( is_wp_error( $recipe ) ) {
			return $recipe;
		}

		return rest_ensure_response( ( new RecipeGroupService() )->setup( $recipe ) );
	}

	public function run_setup( $request ) {
		$recipe = $this->recipe( (int) $request['id'] );
		if ( is_wp_error( $recipe ) ) {
			return $recipe;
		}

		try {
			$result = ( new RecipeGroupService() )->install( $recipe, (array) ( $request->get_json_params() ?? [] ) );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'invalid_setup', $e->getMessage(), [ 'status' => 400 ] );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'setup_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * @return Recipe|WP_Error
	 */
	private function recipe( int $id ) {
		$recipe = Recipe::find( $id );

		if ( ! $recipe ) {
			return new WP_Error( 'not_found', __( 'Recipe not found.', 'zaplane' ), [ 'status' => 404 ] );
		}

		return $recipe;
	}
}
