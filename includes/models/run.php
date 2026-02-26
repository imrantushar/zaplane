<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;
use Zaplane\Framework\Database\ORM\Collection;

if (!defined('ABSPATH')) exit;

class Run extends Model
{
    protected static string $table = 'runs';

    protected static array $fillable = [
        'workflow_version_hash',
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

    public function nodeRuns(): Collection
    {
        return NodeRun::where('run_id', $this->id)->orderBy('id', 'asc')->get();
    }

    public function executionEdges(): Collection
    {
        return ExecutionEdge::where('run_id', $this->id)->get();
    }

    public function workflowVersion(): ?WorkflowVersion
    {
        return WorkflowVersion::where('graph_hash', $this->workflow_version_hash)->first();
    }

    public function markAsCompleted(): bool
    {
        $this->status = 'completed';
        $this->finished_at = current_time('mysql');
        return $this->save();
    }

    public function markAsFailed(string $error): bool
    {
        $this->status = 'failed';
        $this->last_error = $error;
        $this->finished_at = current_time('mysql');
        return $this->save();
    }

    public function incrementAttempts(): bool
    {
        $this->attempts = ($this->attempts ?? 0) + 1;
        return $this->save();
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public static function running(): Collection
    {
        return static::where('status', 'running')->get();
    }

    public static function forWorkflowVersion(string $hash): Collection
    {
        return static::where('workflow_version_hash', $hash)
            ->orderBy('id', 'desc')
            ->get();
    }

    public static function recent(int $limit = 100): Collection
    {
        return static::orderBy('id', 'desc')->limit($limit)->get();
    }

    public static function latestTestNodeRuns(string $hash): array
    {
        $testRuns = static::where('workflow_version_hash', $hash)
            ->where('is_test', 1)
            ->orderBy('id', 'desc')
            ->get();

        $nodeOutputs = [];

        foreach ($testRuns as $run) {
            $nodeRuns = $run->nodeRuns();
            foreach ($nodeRuns as $nodeRun) {
                $key = $nodeRun->node_key;
                if (!isset($nodeOutputs[$key]) && $nodeRun->isCompleted()) {
                    $nodeOutputs[$key] = $nodeRun;
                }
            }
        }

        return $nodeOutputs;
    }

    public static function latestTestNodeRunsByWorkflow(int $workflowId): array
    {
        $testRuns = static::where('workflow_id', $workflowId)
            ->where('is_test', 1)
            ->orderBy('id', 'desc')
            ->get();

        $nodeOutputs = [];

        foreach ($testRuns as $run) {
            $nodeRuns = $run->nodeRuns();
            foreach ($nodeRuns as $nodeRun) {
                $key = $nodeRun->node_key;
                if (!isset($nodeOutputs[$key]) && $nodeRun->isCompleted()) {
                    $nodeOutputs[$key] = $nodeRun;
                }
            }
        }

        return $nodeOutputs;
    }

    public static function latestNodeOutputs(string $hash): array
    {
        $runs = static::where('workflow_version_hash', $hash)
            ->whereIn('status', ['completed', 'failed', 'running'])
            ->orderBy('id', 'desc')
            ->get();

        $nodeOutputs = [];

        foreach ($runs as $run) {
            $nodeRuns = $run->nodeRuns();
            foreach ($nodeRuns as $nodeRun) {
                $key = $nodeRun->node_key;
                if (!isset($nodeOutputs[$key]) && $nodeRun->isCompleted()) {
                    $nodeOutputs[$key] = $nodeRun;
                }
            }
        }

        return $nodeOutputs;
    }
}
