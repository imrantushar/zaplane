<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;
use Zaplane\Framework\Database\ORM\Collection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Run extends Model {

	protected static string $table = 'runs';

	protected static array $fillable = [
		'workflow_version_id',
		'workflow_id',
		'target_node_key',
		'start_node_key',
		'status',
		'is_test',
		'trigger_data',
		'attempts',
		'started_at',
		'finished_at',
		'last_error',
	];

	protected static array $casts = [
		'id' => 'integer',
		'workflow_version_id' => 'integer',
		'workflow_id' => 'integer',
		'start_node_key' => 'integer',
		'target_node_key' => 'integer',
		'attempts' => 'integer',
		'is_test' => 'boolean',
		'trigger_data' => 'json',
	];

	protected static bool $timestamps = false;
	protected static string $createdAt = 'started_at';
	protected static string $updatedAt = 'finished_at';

	public function nodeRuns(): Collection {
		return NodeRun::where( 'run_id', $this->id )->orderBy( 'id', 'asc' )->get();
	}

	public function workflowVersion(): ?WorkflowVersion {
		return WorkflowVersion::find( $this->workflow_version_id );
	}

	public function markAsCompleted(): bool {
		$this->status = 'completed';
		$this->finished_at = current_time( 'mysql' );
		return $this->save();
	}

	public function markAsFailed( string $error ): bool {
		$this->status = 'failed';
		$this->last_error = $error;
		$this->finished_at = current_time( 'mysql' );
		return $this->save();
	}

	public function incrementAttempts(): bool {
		$this->attempts = ( $this->attempts ?? 0 ) + 1;
		return $this->save();
	}

	public function isRunning(): bool {
		return 'running' === $this->status;
	}

	public function isCompleted(): bool {
		return 'completed' === $this->status;
	}

	public function isFailed(): bool {
		return 'failed' === $this->status;
	}

	public static function running(): Collection {
		return static::where( 'status', 'running' )->get();
	}

	public static function forWorkflowVersion( int $versionId ): Collection {
		return static::where( 'workflow_version_id', $versionId )
			->orderBy( 'id', 'desc' )
			->get();
	}

	public static function recent( int $limit = 100 ): Collection {
		return static::orderBy( 'id', 'desc' )->limit( $limit )->get();
	}

	public static function latestTestNodeRuns( int $versionId ): array {
		$testRuns = static::where( 'workflow_version_id', $versionId )
			->where( 'is_test', 1 )
			->orderBy( 'id', 'desc' )
			->get();

		$nodeOutputs = [];

		foreach ( $testRuns as $run ) {
			$nodeRuns = $run->nodeRuns();
			foreach ( $nodeRuns as $nodeRun ) {
				$key = $nodeRun->node_key;
				if ( ! isset( $nodeOutputs[ $key ] ) && $nodeRun->isCompleted() ) {
					$nodeOutputs[ $key ] = $nodeRun;
				}
			}
		}

		return $nodeOutputs;
	}

	public static function latestTestNodeRunsByWorkflow( int $workflowId, array $nodeIds = [] ): array {
		$testRuns = static::where( 'workflow_id', $workflowId )
			->where( 'is_test', 1 )
			->orderBy( 'id', 'desc' );

		if ( $nodeIds ) {
			$testRuns = $testRuns->whereIn( 'target_node_key', $nodeIds );
		}

		$testRuns = $testRuns->get();

		$nodeOutputs = [];

		foreach ( $testRuns as $run ) {
			$nodeRuns = $run->nodeRuns();
			foreach ( $nodeRuns as $nodeRun ) {
				$key = $nodeRun->node_key;
				if ( ! isset( $nodeOutputs[ $key ] ) && $nodeRun->isCompleted() ) {
					$nodeOutputs[ $key ] = $nodeRun;
				}
			}
		}

		return $nodeOutputs;
	}

	/**
	 * Latest completed node outputs across ALL runs of a workflow — test or real.
	 *
	 * Fallback for the "@" variable picker so a real run (an actual form
	 * submission, or a manual "Run") surfaces its captured data even when the user
	 * never used the "Test" flow. Full runs have no target_node_key, so unlike the
	 * test variant we don't filter on it.
	 *
	 * @return array<int,NodeRun>
	 */
	public static function latestNodeRunsByWorkflow( int $workflowId, array $nodeIds = [] ): array {
		$runs = static::where( 'workflow_id', $workflowId )
			->orderBy( 'id', 'desc' )
			->get();

		$nodeOutputs = [];

		foreach ( $runs as $run ) {
			foreach ( $run->nodeRuns() as $nodeRun ) {
				$key = (int) $nodeRun->node_key;
				if ( ! empty( $nodeIds ) && ! in_array( $key, $nodeIds, true ) ) {
					continue;
				}
				if ( ! isset( $nodeOutputs[ $key ] ) && $nodeRun->isCompleted() ) {
					$nodeOutputs[ $key ] = $nodeRun;
				}
			}
		}

		return $nodeOutputs;
	}

	public static function latestTestNodeRunsByWorkflowAndVersion( int $workflowId, int $versionId, array $nodeIds = [] ): array {
		$testRuns = static::where( 'workflow_id', $workflowId )
			->where( 'workflow_version_id', $versionId )
			->where( 'is_test', 1 )
			->orderBy( 'id', 'desc' );

		if ( $nodeIds ) {
			$testRuns = $testRuns->whereIn( 'target_node_key', $nodeIds );
		}

		$testRuns = $testRuns->get();

		$nodeOutputs = [];

		foreach ( $testRuns as $run ) {
			$nodeRuns = $run->nodeRuns();
			foreach ( $nodeRuns as $nodeRun ) {
				$key = $nodeRun->node_key;
				if ( ! isset( $nodeOutputs[ $key ] ) ) {
					$nodeOutputs[ $key ] = $nodeRun;
				}
			}
		}

		return $nodeOutputs;
	}

	public static function latestNodeOutputs( int $versionId, array $nodeIds ): array {
		$runs = static::where( 'workflow_version_id', $versionId )
			->whereIn( 'status', [ 'completed', 'failed', 'running' ] )
			->orderBy( 'id', 'desc' );

		if ( $nodeIds ) {
			$runs = $runs->whereIn( 'target_node_key', $nodeIds );
		}

		$runs = $runs->get();

		$nodeOutputs = [];

		foreach ( $runs as $run ) {
			$nodeRuns = $run->nodeRuns();
			foreach ( $nodeRuns as $nodeRun ) {
				$key = $nodeRun->node_key;
				if ( ! isset( $nodeOutputs[ $key ] ) && $nodeRun->isCompleted() ) {
					$nodeOutputs[ $key ] = $nodeRun;
				}
			}
		}

		return $nodeOutputs;
	}
}
