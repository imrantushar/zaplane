<?php
namespace Zaplane\Modules\API\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


trait WorkflowsSchema {
	public function get_public_item_schema() {
		$schema = array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'attempt',
			'type'                 => 'object',
			'properties'           => array(
				'id' => array(
					'description'  => esc_html__( 'Unique identifier for the workflow.', 'zaplane' ),
					'type'         => 'integer',
					'context'      => array( 'view', 'edit', 'embed' ),
					'readonly'     => true,
				),
				'user_id' => array(
					'description'  => esc_html__( 'Workflow creator user id', 'zaplane' ),
					'type'         => array( 'string', 'integer' ),
				),
                'title' => array(
					'description'  => esc_html__( 'Workflow Title', 'zaplane' ),
					'type'         => 'string',
				),
                'name' => array(
					'description'  => esc_html__( 'Workflow Name', 'zaplane' ),
					'type'         => 'string',
				),
				'status' => array(
					'description'  => esc_html__( 'Workflow Status.', 'zaplane' ),
					'type'         => 'string',
				),
				'flow_json' => array(
					'description'  => esc_html__( 'Workflow JSON Content.', 'zaplane' ),
					'type'         => 'string',
				),
				'created_at' => array(
					'description'  => esc_html__( 'Workflow Created Date.', 'zaplane' ),
					'type'         => 'string',
				),
				'updated_at' => array(
					'description'  => esc_html__( 'Workflow Modified Date.', 'zaplane' ),
					'type'         => 'string',
				),
				
				
			),
		);

		return apply_filters( 'zaplane/api/workflows/public_item_schema', $schema );
	}

	public function get_item_schema() {
		$schema = [
			'id'           => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'user_id'           => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'title'     => [
				'type'   => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'name'     => [
				'type'   => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'status'     => [
				'type'   => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'flow_json'     => [
				'type'   => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'created_at'     => [
				'type'   => 'string',
				'format' => 'date-time',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'updated_at'     => [
				'type'   => 'string',
				'format' => 'date-time',
				'sanitize_callback' => 'sanitize_text_field',
			],
			
		];
		return apply_filters( 'zaplane/api/workflows/item_schema', $schema );
	}

	public function get_collection_params() {
		return array(
			'page'     => array(
				'description'       => __( 'Current page of the collection.', 'zaplane' ),
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
				'minimum'           => 1,
			),
			'per_page' => array(
				'description'       => __( 'Maximum number of items to be returned in result set.', 'zaplane' ),
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'search'   => array(
				'description'       => __( 'Limit results to those matching a string.', 'zaplane' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
