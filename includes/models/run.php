<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;
use Zaplane\Framework\Database\ORM\Collection;

if (!defined('ABSPATH')) exit;

class Run extends Model
{
    protected static string $table = 'runs';

    protected static array $fillable = [
        'workflow_id',
        'workflow_version_hash',
        'target_node_key',
        'start_node_key',
        'status',
        'trigger_data',
        'attempts',
        'started_at',
        'finished_at',
        'last_error',
    ];

    protected static array $casts = [
        'id' => 'integer',
        'workflow_id' => 'integer',
        'attempts' => 'integer',
        'trigger_data' => 'json',
    ];

    protected static bool $timestamps = false;
    protected static string $createdAt = 'started_at';
    protected static string $updatedAt = 'finished_at';

    public function nodeRuns(): array
    {
        return NodeRun::where('run_id', $this->id)->orderBy('id', 'asc')->get();
    }

    public function executionEdges(): array
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

    public static function running(): array
    {
        return static::where('status', 'running')->get();
    }

    public static function forWorkflowVersion(string $hash): array
    {
        return static::where('workflow_version_hash', $hash)
            ->orderBy('id', 'desc')
            ->get();
    }

    public static function recent(int $limit = 100): Collection
    {
        return static::orderBy('id', 'desc')->limit($limit)->get();
    }
}
