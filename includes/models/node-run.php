<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;
use Zaplane\Framework\Database\ORM\Collection;

if (!defined('ABSPATH')) exit;

class NodeRun extends Model
{
    protected static string $table = 'node_runs';

    protected static array $fillable = [
        'run_id',
        'node_key',
        'parent_node_run_id',
        'iteration',
        'status',
        'input_json',
        'output_json',
        'attempts',
        'max_attempts',
        'resume_at',
        'started_at',
        'finished_at',
    ];

    protected static array $casts = [
        'id' => 'integer',
        'run_id' => 'integer',
        'node_key' => 'integer',
        'parent_node_run_id' => 'integer',
        'iteration' => 'integer',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'input_json' => 'json',
        'output_json' => 'json',
    ];

    protected static bool $timestamps = false;
    protected static string $createdAt = 'started_at';
    protected static string $updatedAt = 'finished_at';

    public function run(): ?Run
    {
        return Run::find($this->run_id);
    }

    public function parent(): ?self
    {
        if (!$this->parent_node_run_id) {
            return null;
        }
        return static::find($this->parent_node_run_id);
    }

    public function children(): array
    {
        return static::where('parent_node_run_id', $this->id)->get();
    }

    public function logs(): array
    {
        return NodeLog::where('node_run_id', $this->id)->orderBy('id', 'asc')->get();
    }

    public function getInput(): array
    {
        return $this->input_json ?? [];
    }

    public function getOutput(): array
    {
        return $this->output_json ?? [];
    }

    public function setInput(array $input): bool
    {
        $this->input_json = $input;
        return $this->save();
    }

    public function setOutput(array $output): bool
    {
        $this->output_json = $output;
        $this->status = 'completed';
        $this->finished_at = current_time('mysql');
        return $this->save();
    }

    public function markAsRunning(): bool
    {
        $this->status = 'running';
        $this->started_at = current_time('mysql');
        return $this->save();
    }

    public function markAsFailed(string $error = null): bool
    {
        $this->status = 'failed';
        $this->finished_at = current_time('mysql');

        if ($error) {
            $this->log('error', $error);
        }

        return $this->save();
    }

    public function markAsWaiting(?string $resumeAt = null): bool
    {
        $this->status = 'waiting';
        if ($resumeAt) {
            $this->resume_at = $resumeAt;
        }
        return $this->save();
    }

    public function incrementAttempts(): bool
    {
        $this->attempts = ($this->attempts ?? 0) + 1;
        return $this->save();
    }

    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
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

    public function isWaiting(): bool
    {
        return $this->status === 'waiting';
    }

    public function log(string $level, string $message): void
    {
        NodeLog::create([
            'node_run_id' => $this->id,
            'level' => $level,
            'message' => $message,
        ]);
    }

    public static function pending(): array
    {
        return static::where('status', 'pending')->get();
    }

    public static function forRun(int $runId): Collection
    {
        return static::where('run_id', $runId)->orderBy('id', 'asc')->get();
    }
}
