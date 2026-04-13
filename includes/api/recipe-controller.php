<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Models\Recipe;
use Zaplane\Models\Workflow;
use Zaplane\Services\BlueprintService;

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
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_items( $request ) {
		$page    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );

		$total   = Recipe::count();
		$recipes = Recipe::orderBy( 'title', 'asc' )
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

		$recipe->delete();

		return rest_ensure_response( [ 'deleted' => true, 'id' => (int) $request['id'] ] );
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

		$recipe = Recipe::create( [
			'title'        => $title,
			'description'  => $description,
			'thumbnail_id' => $thumbnailId ? (int) $thumbnailId : null,
			'blueprint'    => wp_json_encode( $blueprint ),
			'created_by'   => get_current_user_id(),
		] );

		return rest_ensure_response( $recipe->toResponse() );
	}

	public function recipe_to_workflow( $request ) {
		$recipe = Recipe::find( (int) $request['id'] );
		if ( ! $recipe ) {
			return new WP_Error( 'not_found', 'Recipe not found.', [ 'status' => 404 ] );
		}

		$blueprint = $recipe->getBlueprint();

		if ( empty( $blueprint ) ) {
			return new WP_Error( 'empty_blueprint', 'Recipe blueprint is empty or corrupted.', [ 'status' => 422 ] );
		}

		$titleOverride = sanitize_text_field( $request->get_param( 'title' ) ?? '' );

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
}
