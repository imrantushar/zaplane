<?php
namespace Zaplane\API;

use WP_REST_Controller;
use Zaplane\Classes\IntegrationLoader;
use Zaplane\API\Schema\WorkflowsSchema;
use Zaplane\Query\WorkFlows as WorkFlowsQuery;

if ( ! defined( 'ABSPATH' ) ) exit;

class WorkflowsController extends WP_REST_Controller {
	use WorkflowsSchema;
    public function register_routes() {

        $namespace = 'zaplane/v1';
        $rest_base = 'workflows';
        
        register_rest_route(
			$namespace,
			'/' . $rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_item_schema(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		$schema        = $this->get_item_schema();
		$get_item_args = array(
			'context' => $this->get_context_param( array( 'default' => 'view' ) ),
		);
		if ( isset( $schema['properties']['password'] ) ) {
			$get_item_args['password'] = array(
				'description' => esc_html__( 'The password for the post if it is password protected.', 'academy' ),
				'type'        => 'string',
			);
		}

        register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => esc_html__( 'Unique identifier for the object.', 'academy' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $get_item_args,
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_item_schema(),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'force' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => esc_html__( 'Whether to bypass Trash and force deletion.', 'academy' ),
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
    }


    public function permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden_context',
				esc_html__( 'Sorry, you are not allowed to edit posts in this post type.', 'academy' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}


	/**
	 * Retrieves a collection of posts.
	 *
	 * @since 4.7.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or \WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$data = WorkflowsQuery::get_all();
		$total = WorkFlowsQuery::count();
		rest_ensure_response( $data );
		$response = rest_ensure_response( $data );
		$response->header( 'x-wp-total', $total );
		return $response;
	}

	public function get_item( $request ) {
		$ID = (int) $request->get_param( 'id' );
		$data = WorkflowsQuery::get($ID);
		return rest_ensure_response( $data );
	}

	/**
	 * Creates a single post.
	 *
	 * @since 4.7.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return \WP_Error Response object on success, or \WP_Error object on failure.
	 */
	public function create_item( $request ) {
		$prepared_workflow = (array) $this->prepare_item_for_database( $request );
		$workflow_id = WorkflowsQuery::create($prepared_workflow);
		$data = WorkflowsQuery::get($workflow_id);
		return rest_ensure_response( $data );
	}

	public function update_item( $request ) {
		
	}
	public function delete_item( $request ) {
		$ID = (int) $request->get_param( 'id' );
		$old = WorkflowsQuery::get($ID);
		$response = WorkflowsQuery::delete($ID);
		return rest_ensure_response( [
			'is_deleted' => $response,
			'old' => $old
		] );
	}

	protected function prepare_item_for_database( $request ) {
		$workflow  = new \stdClass();

		$schema = $this->get_item_schema();

		// ID.
		if ( ! empty( $schema['id'] ) && isset( $request['id'] ) ) {
			if ( is_numeric( $request['id'] ) ) {
				$workflow->id = (int) $request['id'];
			}
		}

		// Author.
		if ( ! empty( $schema['user_id'] ) && isset( $request['user_id'] ) ) {
			if ( is_string( $request['user_id'] ) ) {
				$workflow->user_id = $request['user_id'];
			}
		}

	

		// Title.
		if ( ! empty( $schema['title'] ) && isset( $request['title'] ) ) {
			if ( is_string( $request['title'] ) ) {
				$workflow->title = $request['title'];
			}
		}

		// Name
		if ( ! empty( $schema['name'] ) && isset( $request['name'] ) ) {
			if ( is_string( $request['name'] ) ) {
				$workflow->name = $request['name'];
			}
		}

		// Content.
		if ( ! empty( $schema['flow_json'] ) && isset( $request['flow_json'] ) ) {
			if ( is_string( $request['flow_json'] ) ) {
				$workflow->flow_json = $request['flow_json'];
			}
		}

		// Status.
		if ( ! empty( $schema['status'] ) && isset( $request['status'] ) ) {
			if ( is_string( $request['status'] ) ) {
				$workflow->status = $request['status'];
			}
		}

		
		// Date Created.
		if ( ! empty( $schema['created_at'] ) && isset( $request['created_at'] ) ) {
			if ( is_string( $request['created_at'] ) ) {
				$workflow->created_at = $request['created_at'];
			}
		}

		// Date Modified
		if ( ! empty( $schema['modified_at'] ) && isset( $request['modified_at'] ) ) {
			if ( is_string( $request['modified_at'] ) ) {
				$workflow->modified_at = $request['modified_at'];
			}
		}

		return apply_filters( 'zaplane/api/workflows/rest_pre_insert_workflow', $workflow, $request, $schema );
	}
}
