<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Models\Folder;
use Zaplane\Models\Workflow;
use Zaplane\Models\Run;
use Zaplane\Authoring\WorkflowAuthor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FolderController extends WP_REST_Controller {

	protected ?Container $container;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
		$this->namespace = 'zaplane/v1';
		$this->rest_base = 'folders';
	}

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
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
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'title' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/workflows/(?P<workflow_id>\d+)', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add_workflow' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'remove_workflow' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/workflows', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_folder_workflows' ],
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

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'title' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/status', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'set_status' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'status' => [
						'type'     => 'string',
						'required' => true,
						'enum'     => [ 'active', 'paused' ],
					],
				],
			],
		] );
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Switches on every workflow in the folder, or pauses the ones that are on.
	 *
	 * A workflow only goes live if it passes the checks any workflow has to pass
	 * to go live. One that doesn't keeps its status, and says why.
	 */
	public function set_status( $request ) {
		$folder = Folder::find( (int) $request['id'] );
		if ( ! $folder ) {
			return new WP_Error( 'not_found', 'Folder not found.', [ 'status' => 404 ] );
		}

		$status  = (string) $request->get_param( 'status' );
		$results = [];

		foreach ( $folder->workflows() as $workflow ) {
			$result = [
				'id'     => (int) $workflow->id,
				'title'  => (string) $workflow->title,
				'status' => (string) $workflow->status,
				'error'  => null,
			];

			// Pausing stops what is running; a draft stays a draft.
			$changes = 'active' === $status ? 'active' !== $workflow->status : 'active' === $workflow->status;

			if ( $changes ) {
				try {
					$result['status'] = (string) WorkflowAuthor::set_status( (int) $workflow->id, $status )['status'];
				} catch ( \InvalidArgumentException $e ) {
					$result['error'] = $e->getMessage();
				}
			}

			$results[] = $result;
		}

		return rest_ensure_response( [ 'workflows' => $results ] );
	}

	public function get_items( $request ) {
		$page    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );

		$total   = Folder::count();
		$folders = Folder::orderBy( 'title', 'asc' )
			->forPage( $page, $perPage )
			->get()
			->map( fn( $f ) => $f->toResponse() )
			->toArray();

		return rest_ensure_response( [
			'data'       => $folders,
			'pagination' => [
				'page'        => $page,
				'per_page'    => $perPage,
				'total'       => $total,
				'total_pages' => (int) ceil( $total / $perPage ),
			],
		] );
	}

	public function create_item( $request ) {
		$title = $request->get_param( 'title' );

		if ( ! $title ) {
			return new WP_Error( 'missing_title', 'Folder title is required.', [ 'status' => 400 ] );
		}

		$folder = Folder::create( [
			'title'      => $title,
			'created_by' => get_current_user_id(),
		] );

		return rest_ensure_response( $folder->toResponse() );
	}

	public function update_item( $request ) {
		$folder = Folder::find( (int) $request['id'] );
		if ( ! $folder ) {
			return new WP_Error( 'not_found', 'Folder not found.', [ 'status' => 404 ] );
		}

		$params = $request->get_json_params() ?? [];

		if ( isset( $params['title'] ) ) {
			$folder->title = sanitize_text_field( $params['title'] );
		}

		$folder->save();

		return rest_ensure_response( $folder->toResponse() );
	}

	public function add_workflow( $request ) {
		$folder = Folder::find( (int) $request['id'] );
		if ( ! $folder ) {
			return new \WP_Error( 'not_found', 'Folder not found.', [ 'status' => 404 ] );
		}

		$workflow = Workflow::find( (int) $request['workflow_id'] );
		if ( ! $workflow ) {
			return new \WP_Error( 'workflow_not_found', 'Workflow not found.', [ 'status' => 404 ] );
		}

		$workflow->folder_id = $folder->id;
		$workflow->save();

		return rest_ensure_response( $workflow->toArray() );
	}

	public function remove_workflow( $request ) {
		$folder = Folder::find( (int) $request['id'] );
		if ( ! $folder ) {
			return new \WP_Error( 'not_found', 'Folder not found.', [ 'status' => 404 ] );
		}

		$workflow = Workflow::find( (int) $request['workflow_id'] );
		if ( ! $workflow ) {
			return new \WP_Error( 'workflow_not_found', 'Workflow not found.', [ 'status' => 404 ] );
		}

		$workflow->folder_id = null;
		$workflow->save();

		return rest_ensure_response( [
			'removed' => true,
			'workflow_id' => $workflow->id
		] );
	}

	public function get_folder_workflows( $request ) {
		$folder = Folder::find( (int) $request['id'] );
		if ( ! $folder ) {
			return new \WP_Error( 'not_found', 'Folder not found.', [ 'status' => 404 ] );
		}

		$page    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );

		$total     = Workflow::where( 'folder_id', $folder->id )->count();
		$workflows = Workflow::where( 'folder_id', $folder->id )
			->orderBy( 'id', 'desc' )
			->forPage( $page, $perPage )
			->get();

		$data = [];
		foreach ( $workflows as $workflow ) {
			$item    = $workflow->toArray();
			$version = $workflow->activeVersion();

			if ( $version ) {
				$item['success_runs'] = Run::where( 'workflow_version_id', $version->id )
					->where( 'status', 'completed' )
					->count();
				$item['failed_runs']  = Run::where( 'workflow_version_id', $version->id )
					->where( 'status', 'failed' )
					->count();
			} else {
				$item['success_runs'] = 0;
				$item['failed_runs']  = 0;
			}

			$data[] = $item;
		}

		return rest_ensure_response( [
			'data'       => $data,
			'pagination' => [
				'page'        => $page,
				'per_page'    => $perPage,
				'total'       => $total,
				'total_pages' => (int) ceil( $total / $perPage ),
			],
		] );
	}

	public function delete_item( $request ) {
		$folder = Folder::find( (int) $request['id'] );
		if ( ! $folder ) {
			return new WP_Error( 'not_found', 'Folder not found.', [ 'status' => 404 ] );
		}

		if ( ! $folder->isDeletable() ) {
			return new WP_Error(
				'folder_not_empty',
				'Cannot delete a folder that still has workflows assigned. Move or remove the workflows first.',
				[ 'status' => 409 ]
			);
		}

		$folder->delete();

		return rest_ensure_response( [
			'deleted' => true,
			'id' => (int) $request['id']
		] );
	}
}
