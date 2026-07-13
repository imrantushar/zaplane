<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Models\RecipeFolder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RecipeFolderController extends WP_REST_Controller {

	protected ?Container $container;

	public function __construct( ?Container $container = null ) {
		$this->container  = $container;
		$this->namespace  = 'zaplane/v1';
		$this->rest_base  = 'recipe-folders';
	}

	public function register_routes(): void {
		// Collection
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'title'     => [
						'type'     => 'string',
						'required' => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'parent_id' => [
						'type'     => 'integer',
						'required' => false,
						'default'  => null,
					],
				],
			],
		] );

		// Single item
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'title'     => [
						'type'    => 'string',
						'required' => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'parent_id' => [
						'type'    => 'integer',
						'required' => false,
					],
				],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	// -------------------------------------------------------------------------
	// GET /recipe-folders — full nested tree
	// -------------------------------------------------------------------------

	public function get_items( $request ) {
		return rest_ensure_response( [
			'folders' => RecipeFolder::tree(),
		] );
	}

	// -------------------------------------------------------------------------
	// POST /recipe-folders
	// -------------------------------------------------------------------------

	public function create_item( $request ) {
		$title     = $request->get_param( 'title' );
		$parentId  = $request->get_param( 'parent_id' );

		if ( ! $title ) {
			return new WP_Error( 'missing_title', 'Folder title is required.', [ 'status' => 400 ] );
		}

		// Validate parent exists when provided.
		if ( $parentId ) {
			$parent = RecipeFolder::find( (int) $parentId );
			if ( ! $parent ) {
				return new WP_Error( 'parent_not_found', 'Parent folder not found.', [ 'status' => 404 ] );
			}
		}

		$folder = RecipeFolder::create( [
			'title'      => $title,
			'parent_id'  => $parentId ? (int) $parentId : null,
			'created_by' => get_current_user_id(),
		] );

		return rest_ensure_response( $folder->toArray() );
	}

	// -------------------------------------------------------------------------
	// PUT /recipe-folders/{id}
	// -------------------------------------------------------------------------

	public function update_item( $request ) {
		$folder = RecipeFolder::find( (int) $request['id'] );
		if ( ! $folder ) {
			return new WP_Error( 'not_found', 'Folder not found.', [ 'status' => 404 ] );
		}

		$params = $request->get_json_params() ?? [];

		if ( isset( $params['title'] ) ) {
			$folder->title = sanitize_text_field( $params['title'] );
		}

		if ( array_key_exists( 'parent_id', $params ) ) {
			$newParentId = $params['parent_id'] ? (int) $params['parent_id'] : null;

			// Prevent moving a folder into itself or its own descendant.
			if ( $newParentId && $this->is_descendant( $folder->id, $newParentId ) ) {
				return new WP_Error(
					'circular_reference',
					'A folder cannot be moved into itself or one of its sub-folders.',
					[ 'status' => 400 ]
				);
			}

			if ( $newParentId ) {
				$parent = RecipeFolder::find( $newParentId );
				if ( ! $parent ) {
					return new WP_Error( 'parent_not_found', 'Parent folder not found.', [ 'status' => 404 ] );
				}
			}

			$folder->parent_id = $newParentId;
		}//end if

		$folder->save();

		return rest_ensure_response( $folder->toArray() );
	}

	// -------------------------------------------------------------------------
	// DELETE /recipe-folders/{id}
	// -------------------------------------------------------------------------

	public function delete_item( $request ) {
		$folder = RecipeFolder::find( (int) $request['id'] );
		if ( ! $folder ) {
			return new WP_Error( 'not_found', 'Folder not found.', [ 'status' => 404 ] );
		}

		if ( ! $folder->isDeletable() ) {
			return new WP_Error(
				'folder_not_empty',
				'Cannot delete a folder that still contains recipes or sub-folders. Move or delete the contents first.',
				[ 'status' => 409 ]
			);
		}

		$folder->delete();

		return rest_ensure_response( [
			'deleted' => true,
			'id' => (int) $request['id']
		] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Check whether $candidateId is an ancestor of (or equal to) $folderId.
	 * Used to prevent circular parent relationships.
	 */
	private function is_descendant( int $folderId, int $candidateId ): bool {
		if ( $folderId === $candidateId ) {
			return true;
		}

		$children = RecipeFolder::where( 'parent_id', $folderId )->get();
		foreach ( $children as $child ) {
			if ( $this->is_descendant( $child->id, $candidateId ) ) {
				return true;
			}
		}

		return false;
	}
}
