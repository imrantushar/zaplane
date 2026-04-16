<?php

namespace Zaplane\Services;

use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;
use Zaplane\Models\Connection;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BlueprintService
 *
 * Shared serialization and import logic used by both the ImportExportController
 * and the RecipeController.  The ImportExportController keeps its own private
 * copy of these methods for backwards compatibility; this class is the single
 * source of truth going forward.
 */
class BlueprintService {

	// -------------------------------------------------------------------------
	// Serialize: Workflow → blueprint array
	// -------------------------------------------------------------------------

	/**
	 * Serialize a single workflow into the standard blueprint format.
	 *
	 * @param Workflow $workflow
	 * @param string   $versionsScope  'active' | 'all'
	 * @param bool     $includeRuns
	 * @return array
	 */
	public function serialize( Workflow $workflow, string $versionsScope = 'active', bool $includeRuns = false ): array {
		$query = WorkflowVersion::where( 'workflow_id', $workflow->id )->orderBy( 'id', 'asc' );

		if ( 'active' === $versionsScope ) {
			$query = $query->where( 'is_active', 1 );
		}

		$versions = $query->get();

		$connectionIds    = [];
		$exportedVersions = [];

		foreach ( $versions as $version ) {
			$graph = $version->getGraph();

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
				$versionData['runs'] = $this->serialize_runs( $version->id );
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

	private function serialize_runs( int $versionId ): array {
		$runs         = Run::where( 'workflow_version_id', $versionId )->orderBy( 'id', 'asc' )->get();
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

	// -------------------------------------------------------------------------
	// Import: blueprint array → new Workflow
	// -------------------------------------------------------------------------

	/**
	 * Import a single workflow from a blueprint and return the new workflow.
	 *
	 * @param array  $data         Blueprint array (as produced by serialize()).
	 * @param string $titleOverride Optional title override.
	 * @return Workflow
	 * @throws \InvalidArgumentException
	 */
	public function import( array $data, string $titleOverride = '' ): Workflow {
		if ( empty( $data['title'] ) && empty( $titleOverride ) ) {
			throw new \InvalidArgumentException( 'Workflow title is missing.' );
		}

		if ( empty( $data['versions'] ) || ! is_array( $data['versions'] ) ) {
			throw new \InvalidArgumentException( 'Workflow versions data is missing.' );
		}

		$title = $titleOverride ?: sanitize_text_field( $data['title'] );

		$workflow = Workflow::create( [
			'user_id' => get_current_user_id(),
			'title'   => $title,
			'status'  => 'draft',
			'layout'  => sanitize_text_field( $data['layout'] ?? 'LR' ),
		] );

		foreach ( $data['versions'] as $versionData ) {
			$graph = $versionData['graph_json'] ?? [ 'nodes' => [], 'edges' => [] ];
			$graph = $this->strip_connection_ids( $graph );
			$hash  = hash( 'sha256', wp_json_encode( $graph ) );

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
		}

		// Ensure at least one version is active.
		$hasActive = WorkflowVersion::where( 'workflow_id', $workflow->id )->where( 'is_active', 1 )->first();
		if ( ! $hasActive ) {
			$last = WorkflowVersion::where( 'workflow_id', $workflow->id )->orderBy( 'id', 'desc' )->first();
			if ( $last ) {
				$last->is_active = 1;
				$last->save();
			}
		}

		return $workflow;
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
