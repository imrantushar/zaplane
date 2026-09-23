<?php

namespace Zaplane\Ajax;

use Zaplane\Framework\Classes\AbstractAjaxHandler;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Workflows extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'update_workflow_status' => [
				'callback' => [ $this, 'updateStatus' ],
				'capability' => 'manage_options',
				'fields' => [
					'id' => 'absint',
					'status' => 'string',
				],
			],

			'duplicate_workflow' => [
				'callback' => [ $this, 'duplicateWorkflow' ],
				'capability' => 'manage_options',
				'fields' => [
					'id' => 'absint',
					'new_title' => 'string',
				],
			],

			'update_workflow_title' => [
				'callback' => [ $this, 'updateWorkflowTitle' ],
				'capability' => 'manage_options',
				'fields' => [
					'id' => 'absint',
					'title' => 'string',
				],
			],

			'get_workflow_stats' => [
				'callback' => [ $this, 'getStats' ],
				'capability' => 'manage_options',
				'fields' => [
					'workflow_id' => 'absint',
				],
			],
		];
	}

	public function updateStatus( array $payload ) {
		$id     = $payload['id'] ?? 0;
		$status = $payload['status'] ?? '';

		if ( ! $id || empty( $status ) ) {
			return new \WP_Error( 'missing_params', __( 'ID and status are required', 'zaplane' ), [ 'code' => 400 ] );
		}

		if ( ! in_array( $status, [ 'active', 'draft', 'paused' ], true ) ) {
			return new \WP_Error( 'invalid_status', __( 'Invalid status value', 'zaplane' ), [ 'code' => 400 ] );
		}

		$workflow = Workflow::find( $id );

		if ( ! $workflow ) {
			return new \WP_Error( 'not_found', __( 'Workflow not found', 'zaplane' ), [ 'code' => 404 ] );
		}

		$previousStatus = $workflow->status;

		// The same checks as the editor and "Turn all on": a workflow that
		// can't run (an invalid step, an app with no connection) doesn't go live.
		try {
			\Zaplane\Authoring\WorkflowAuthor::set_status( $id, $status );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'cannot_activate', $e->getMessage(), [ 'code' => 400 ] );
		}
		$workflow = Workflow::find( $id );

		if ( 'draft' === $previousStatus && 'active' === $status ) {
			$draftVersion = WorkflowVersion::where( 'workflow_id', $id )->where( 'is_active', 1 )->first();

			if ( $draftVersion ) {
				$graph = $draftVersion->getGraph();
				$hash  = hash( 'sha256', wp_json_encode( $graph ) );

				$draftVersion->is_active = 0;
				$draftVersion->save();

				WorkflowVersion::create([
					'workflow_id'    => $id,
					'graph_json'     => $graph,
					'graph_hash'     => $hash,
					'is_active'      => 1,
					'version_number' => 1,
				]);
			}
		}

		return [
			'id'      => $workflow->id,
			'status'  => $workflow->status,
			'message' => __( 'Status updated successfully', 'zaplane' ),
		];
	}

	public function duplicateWorkflow( array $payload ) {
		$id = $payload['id'] ?? 0;

		if ( ! $id ) {
			return new \WP_Error( 'missing_id', __( 'Workflow ID is required', 'zaplane' ), [ 'code' => 400 ] );
		}

		$original = Workflow::find( $id );

		if ( ! $original ) {
			return new \WP_Error( 'not_found', __( 'Workflow not found', 'zaplane' ), [ 'code' => 404 ] );
		}

		$newTitle = ! empty( $payload['new_title'] ) ? $payload['new_title'] : $original->title . ' (Copy)';

		$duplicate = Workflow::create([
			'user_id' => get_current_user_id(),
			'title' => $newTitle,
			'name' => sanitize_title( $newTitle ),
			'status' => 'draft',
		]);

		$activeVersion = $original->activeVersion();
		if ( $activeVersion ) {
			WorkflowVersion::create([
				'workflow_id' => $duplicate->id,
				'graph_json' => $activeVersion->graph_json,
				'graph_hash' => hash( 'sha256', wp_json_encode( $activeVersion->graph_json ) ),
				'is_active' => 1,
			]);
		}

		return [
			'id' => $duplicate->id,
			'title' => $duplicate->title,
			'message' => __( 'Workflow duplicated successfully', 'zaplane' ),
		];
	}

	public function updateWorkflowTitle( array $payload ) {
		$id = $payload['id'] ?? 0;
		$title = $payload['title'] ?? '';

		if ( ! $id || empty( $title ) ) {
			return new \WP_Error( 'missing_params', __( 'ID and title are required', 'zaplane' ), [ 'code' => 400 ] );
		}

		$workflow = Workflow::find( $id );

		if ( ! $workflow ) {
			return new \WP_Error( 'not_found', __( 'Workflow not found', 'zaplane' ), [ 'code' => 404 ] );
		}

		$workflow->title = $title;
		$workflow->save();

		return [
			'id' => $workflow->id,
			'title' => $workflow->title,
			'message' => __( 'Title updated successfully', 'zaplane' ),
		];
	}
	public function getStats( array $payload ) {
		$workflowId = $payload['workflow_id'] ?? 0;

		if ( ! $workflowId ) {
			return new \WP_Error( 'missing_id', __( 'Workflow ID is required', 'zaplane' ), [ 'code' => 400 ] );
		}

		$workflow = Workflow::find( $workflowId );

		if ( ! $workflow ) {
			return new \WP_Error( 'not_found', __( 'Workflow not found', 'zaplane' ), [ 'code' => 404 ] );
		}

		return [
			'id' => $workflow->id,
			'title' => $workflow->title,
			'status' => $workflow->status,
			'created_at' => $workflow->created_at,
		];
	}
}
