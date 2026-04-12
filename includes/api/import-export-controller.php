<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;
use Zaplane\Models\Connection;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ImportExportController extends WP_REST_Controller {

	protected ?Container $container = null;

	const EXPORT_FORMAT_VERSION = '1.0';

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
	}

	public function register_routes() {
		$namespace = 'zaplane/v1';

		register_rest_route( $namespace, '/export', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'export_workflows' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'workflow_ids' => [
						'type'        => 'array',
						'required'    => false,
						'items'       => [ 'type' => 'integer' ],
						'description' => 'IDs to export. Omit (or pass an empty array) to export all workflows.',
					],
					'include_runs' => [
						'type'    => 'boolean',
						'default' => false,
					],
					'versions' => [
						'type'    => 'string',
						'enum'    => [ 'all', 'active' ],
						'default' => 'all',
					],
				],
			],
		] );

		register_rest_route( $namespace, '/import', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'import_workflows' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
	}

	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	public function export_workflows( $request ) {
		$rawIds        = $request->get_param( 'workflow_ids' );
		$includeRuns   = (bool) $request->get_param( 'include_runs' );
		$versionsScope = $request->get_param( 'versions' ) ?? 'all';

		$exported = [];

		if ( ! empty( $rawIds ) && is_array( $rawIds ) ) {
			// Export specific workflows by ID.
			$workflowIds = array_map( 'absint', $rawIds );

			foreach ( $workflowIds as $workflowId ) {
				$workflow = Workflow::find( $workflowId );
				if ( ! $workflow ) {
					continue;
				}
				$exported[] = $this->export_single_workflow( $workflow, $versionsScope, $includeRuns );
			}
		} else {
			// No IDs provided — export every workflow.
			$workflows = Workflow::orderBy( 'id', 'asc' )->get();

			foreach ( $workflows as $workflow ) {
				$exported[] = $this->export_single_workflow( $workflow, $versionsScope, $includeRuns );
			}
		}

		return rest_ensure_response( [
			'format_version' => self::EXPORT_FORMAT_VERSION,
			'exported_at'    => current_time( 'mysql' ),
			'workflows'      => $exported,
		] );
	}

	private function export_single_workflow( Workflow $workflow, string $versionsScope, bool $includeRuns ): array {
		$query = WorkflowVersion::where( 'workflow_id', $workflow->id )->orderBy( 'id', 'asc' );

		if ( 'active' === $versionsScope ) {
			$query = $query->where( 'is_active', 1 );
		}

		$versions = $query->get();

		$connectionIds    = [];
		$exportedVersions = [];

		foreach ( $versions as $version ) {
			$graph = $version->getGraph();

			// Collect unique connection IDs referenced by nodes.
			foreach ( $graph['nodes'] ?? [] as $node ) {
				$connId = $node['data']['connection_id'] ?? null;
				if ( $connId ) {
					$connectionIds[] = (int) $connId;
				}
			}

			$versionData = [
				'graph_json'     => $graph,
				'graph_hash'     => $version->graph_hash,
				'is_active'      => (bool) $version->is_active,
				'version_number' => $version->version_number,
				'created_at'     => $version->created_at,
			];

			if ( $includeRuns ) {
				$versionData['runs'] = $this->export_runs_for_version( $version->id );
			}

			$exportedVersions[] = $versionData;
		}

		$connectionIds = array_values( array_unique( $connectionIds ) );
		$connections   = [];

		foreach ( $connectionIds as $connId ) {
			$connection = Connection::find( $connId );
			if ( $connection ) {
				$connections[] = [
					'original_id' => $connection->id,
					'app'         => $connection->app,
					'name'        => $connection->name,
					'auth_type'   => $connection->auth_type,
					'status'      => $connection->status,
				];
			}
		}

		return [
			'title'       => $workflow->title,
			'status'      => $workflow->status,
			'layout'      => $workflow->layout,
			'versions'    => $exportedVersions,
			'connections' => $connections,
		];
	}

	private function export_runs_for_version( int $versionId ): array {
		$runs        = Run::where( 'workflow_version_id', $versionId )->orderBy( 'id', 'asc' )->get();
		$exportedRuns = [];

		foreach ( $runs as $run ) {
			$nodeRuns = NodeRun::where( 'run_id', $run->id )
				->orderBy( 'id', 'asc' )
				->get()
				->map( fn( $nr ) => [
					'_id'                => $nr->id,
					'node_key'           => $nr->node_key,
					'parent_node_run_id' => $nr->parent_node_run_id,
					'iteration'          => $nr->iteration,
					'status'             => $nr->status,
					'input_json'         => $nr->getInput(),
					'output_json'        => $nr->getOutput(),
					'attempts'           => $nr->attempts,
					'started_at'         => $nr->started_at,
					'finished_at'        => $nr->finished_at,
				] )
				->toArray();

			$exportedRuns[] = [
				'trigger_data'    => $run->trigger_data,
				'status'          => $run->status,
				'start_node_key'  => $run->start_node_key,
				'target_node_key' => $run->target_node_key,
				'is_test'         => (bool) $run->is_test,
				'started_at'      => $run->started_at,
				'finished_at'     => $run->finished_at,
				'last_error'      => $run->last_error,
				'node_runs'       => $nodeRuns,
			];
		}

		return $exportedRuns;
	}

	private function resolve_import_body( $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return $request->get_json_params() ?? [];
		}

		$file = $files['file'];

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			$messages = [
				UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the server\'s maximum upload size.',
				UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the form\'s maximum upload size.',
				UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
				UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
				UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary folder.',
				UPLOAD_ERR_CANT_WRITE => 'The server failed to write the file to disk.',
				UPLOAD_ERR_EXTENSION  => 'A server extension stopped the file upload.',
			];
			$message = $messages[ $file['error'] ] ?? 'Unknown upload error.';

			return new WP_Error( 'upload_error', $message, [ 'status' => 400 ] );
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( 'json' !== $ext ) {
			return new WP_Error( 'invalid_file_type', 'Only .json files are accepted.', [ 'status' => 400 ] );
		}

		$content = file_get_contents( $file['tmp_name'] );
		if ( false === $content ) {
			return new WP_Error( 'file_read_error', 'Could not read the uploaded file.', [ 'status' => 400 ] );
		}

		$body = json_decode( $content, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'invalid_json',
				'The uploaded file contains invalid JSON: ' . json_last_error_msg(),
				[ 'status' => 400 ]
			);
		}

		return $body;
	}

	public function import_workflows( $request ) {
		$body = $this->resolve_import_body( $request );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		if ( empty( $body['workflows'] ) || ! is_array( $body['workflows'] ) ) {
			return new WP_Error( 'invalid_payload', 'Invalid import payload: missing workflows array', [ 'status' => 400 ] );
		}

		$formatVersion = $body['format_version'] ?? null;
		if ( $formatVersion && version_compare( (string) $formatVersion, self::EXPORT_FORMAT_VERSION, '>' ) ) {
			return new WP_Error(
				'unsupported_format_version',
				sprintf( 'Export format version %s is not supported by this installation.', esc_html( $formatVersion ) ),
				[ 'status' => 400 ]
			);
		}

		$results = [];
		$errors  = [];

		foreach ( $body['workflows'] as $index => $workflowData ) {
			try {
				$results[] = $this->import_single_workflow( $workflowData );
			} catch ( \Throwable $e ) {
				$errors[] = [
					'index' => $index,
					'title' => sanitize_text_field( $workflowData['title'] ?? "Workflow #{$index}" ),
					'error' => $e->getMessage(),
				];
			}
		}

		return rest_ensure_response( [
			'imported' => count( $results ),
			'failed'   => count( $errors ),
			'results'  => $results,
			'errors'   => $errors,
		] );
	}

	private function import_single_workflow( array $data ): array {
		if ( empty( $data['title'] ) ) {
			throw new \InvalidArgumentException( 'Workflow title is missing.' );
		}

		if ( empty( $data['versions'] ) || ! is_array( $data['versions'] ) ) {
			throw new \InvalidArgumentException( 'Workflow versions data is missing.' );
		}

		$workflow = Workflow::create( [
			'user_id' => get_current_user_id(),
			'title'   => sanitize_text_field( $data['title'] ),
			'status'  => 'draft',
			'layout'  => sanitize_text_field( $data['layout'] ?? 'LR' ),
		] );

		$importedVersions = [];

		foreach ( $data['versions'] as $versionData ) {
			$graph = $versionData['graph_json'] ?? [ 'nodes' => [], 'edges' => [] ];

			$graph = $this->strip_connection_ids( $graph );

			$hash = hash( 'sha256', wp_json_encode( $graph ) );

			$version = WorkflowVersion::create( [
				'workflow_id'    => $workflow->id,
				'graph_json'     => $graph,
				'graph_hash'     => $hash,
				'is_active'      => (bool) ( $versionData['is_active'] ?? false ),
				'version_number' => isset( $versionData['version_number'] ) ? (int) $versionData['version_number'] : null,
			] );

			if ( ! empty( $versionData['runs'] ) && is_array( $versionData['runs'] ) ) {
				$this->import_runs( $versionData['runs'], $workflow->id, $version->id );
			}

			$importedVersions[] = [
				'original_hash' => $versionData['graph_hash'] ?? null,
				'new_id'        => $version->id,
				'is_active'     => (bool) $version->is_active,
			];
		}

		$hasActive = WorkflowVersion::where( 'workflow_id', $workflow->id )->where( 'is_active', 1 )->first();
		if ( ! $hasActive ) {
			$last = WorkflowVersion::where( 'workflow_id', $workflow->id )->orderBy( 'id', 'desc' )->first();
			if ( $last ) {
				$last->is_active = 1;
				$last->save();

				$lastIdx = count( $importedVersions ) - 1;
				$importedVersions[ $lastIdx ]['is_active'] = true;
			}
		}

		return [
			'workflow_id'          => $workflow->id,
			'title'                => $workflow->title,
			'versions_imported'    => count( $importedVersions ),
			'versions'             => $importedVersions,
			'connections_to_relink' => $data['connections'] ?? [],
		];
	}

	private function strip_connection_ids( array $graph ): array {
		if ( ! isset( $graph['nodes'] ) || ! is_array( $graph['nodes'] ) ) {
			return $graph;
		}

		foreach ( $graph['nodes'] as &$node ) {
			if ( array_key_exists( 'connection_id', $node['data'] ?? [] ) ) {
				$node['data']['connection_id'] = null;
			}
		}
		unset( $node );

		return $graph;
	}

	private function import_runs( array $runs, int $workflowId, int $versionId ): void {
		foreach ( $runs as $runData ) {
			$run = Run::create( [
				'workflow_version_id' => $versionId,
				'workflow_id'         => $workflowId,
				'trigger_data'        => $runData['trigger_data'] ?? [],
				'status'              => $runData['status'] ?? 'completed',
				'start_node_key'      => $runData['start_node_key'] ?? null,
				'target_node_key'     => $runData['target_node_key'] ?? null,
				'is_test'             => (bool) ( $runData['is_test'] ?? false ),
				'started_at'          => $runData['started_at'] ?? current_time( 'mysql' ),
				'finished_at'         => $runData['finished_at'] ?? null,
				'last_error'          => $runData['last_error'] ?? null,
			] );

			if ( ! empty( $runData['node_runs'] ) && is_array( $runData['node_runs'] ) ) {
				$this->import_node_runs( $runData['node_runs'], $run->id );
			}
		}
	}

	private function import_node_runs( array $nodeRuns, int $runId ): void {
		$idMap    = [];
		$newNodes = [];

		foreach ( $nodeRuns as $nrData ) {
			$nodeRun = NodeRun::create( [
				'run_id'             => $runId,
				'node_key'           => (int) $nrData['node_key'],
				'parent_node_run_id' => null,
				'iteration'          => isset( $nrData['iteration'] ) ? (int) $nrData['iteration'] : null,
				'status'             => $nrData['status'] ?? 'completed',
				'input_json'         => $nrData['input_json'] ?? [],
				'output_json'        => $nrData['output_json'] ?? [],
				'attempts'           => isset( $nrData['attempts'] ) ? (int) $nrData['attempts'] : 1,
				'started_at'         => $nrData['started_at'] ?? null,
				'finished_at'        => $nrData['finished_at'] ?? null,
			] );

			$originalId = $nrData['_id'] ?? null;
			if ( $originalId ) {
				$idMap[ (int) $originalId ] = $nodeRun->id;
			}

			$originalParent = $nrData['parent_node_run_id'] ?? null;
			if ( $originalParent ) {
				$newNodes[ $nodeRun->id ] = (int) $originalParent;
			}
		}

		foreach ( $newNodes as $newId => $originalParentId ) {
			if ( isset( $idMap[ $originalParentId ] ) ) {
				$nodeRun = NodeRun::find( $newId );
				if ( $nodeRun ) {
					$nodeRun->parent_node_run_id = $idMap[ $originalParentId ];
					$nodeRun->save();
				}
			}
		}
	}
}
