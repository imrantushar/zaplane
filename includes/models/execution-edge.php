<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;
use Zaplane\Framework\Database\ORM\Collection;

if (!defined('ABSPATH')) exit;

class ExecutionEdge extends Model
{
    protected static string $table = 'execution_edges';

    protected static array $fillable = [
        'run_id',
        'from_node_run_id',
        'to_node_key',
        'payload_json',
    ];

    protected static array $casts = [
        'id' => 'integer',
        'run_id' => 'integer',
        'from_node_run_id' => 'integer',
        'to_node_key' => 'integer',
        'payload_json' => 'json',
    ];

    protected static bool $timestamps = false;
    protected static string $createdAt = 'created_at';

    public function run(): ?Run
    {
        return Run::find($this->run_id);
    }

    public function fromNodeRun(): ?NodeRun
    {
        return NodeRun::find($this->from_node_run_id);
    }

    public function getPayload(): array
    {
        return $this->payload_json ?? [];
    }

    public static function forRun(int $runId): Collection
    {
        return static::where('run_id', $runId)->get();
    }

    public static function fromNode(int $nodeRunId): Collection
    {
        return static::where('from_node_run_id', $nodeRunId)->get();
    }
}
