<?php

namespace Zaplane\Models;

use Zaplane\Database\ORM\Model;

if (!defined('ABSPATH')) exit;

class NodeLog extends Model
{
    protected static string $table = 'node_logs';

    protected static array $fillable = [
        'node_run_id',
        'level',
        'message',
    ];

    protected static array $casts = [
        'id' => 'integer',
        'node_run_id' => 'integer',
    ];

    protected static bool $timestamps = false;
    protected static string $createdAt = 'created_at';

    public function nodeRun(): ?NodeRun
    {
        return NodeRun::find($this->node_run_id);
    }

    public function isError(): bool
    {
        return $this->level === 'error';
    }

    public function isWarning(): bool
    {
        return $this->level === 'warning';
    }

    public function isInfo(): bool
    {
        return $this->level === 'info';
    }

    public static function forNodeRun(int $nodeRunId): array
    {
        return static::where('node_run_id', $nodeRunId)
            ->orderBy('id', 'asc')
            ->get();
    }

    public static function errors(): array
    {
        return static::where('level', 'error')
            ->orderBy('id', 'desc')
            ->get();
    }
}
