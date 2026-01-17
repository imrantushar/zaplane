<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;
use Zaplane\Framework\Database\ORM\Collection;

if (!defined('ABSPATH')) exit;

class QueueJob extends Model
{
    protected static string $table = 'queue';

    protected static array $fillable = [
        'run_id',
        'node_run_id',
        'available_at',
        'locked_at',
        'lock_token',
        'locked_by',
        'attempts',
        'last_error',
    ];

    protected static array $casts = [
        'id' => 'integer',
        'run_id' => 'integer',
        'node_run_id' => 'integer',
        'attempts' => 'integer',
    ];

    protected static bool $timestamps = false;

    public function run(): ?Run
    {
        return Run::find($this->run_id);
    }

    public function nodeRun(): ?NodeRun
    {
        return NodeRun::find($this->node_run_id);
    }

    public function lock(string $lockedBy): bool
    {
        global $wpdb;

        $token = wp_generate_uuid4();

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE " . static::getTable() . "
             SET locked_at = %s, lock_token = %s, locked_by = %s
             WHERE id = %d AND locked_at IS NULL",
            current_time('mysql'),
            $token,
            $lockedBy,
            $this->id
        ));

        if ($result) {
            $this->locked_at = current_time('mysql');
            $this->lock_token = $token;
            $this->locked_by = $lockedBy;
            return true;
        }

        return false;
    }

    public function unlock(): bool
    {
        $this->locked_at = null;
        $this->lock_token = null;
        $this->locked_by = null;
        return $this->save();
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isAvailable(): bool
    {
        return !$this->isLocked() && strtotime($this->available_at) <= time();
    }

    public function incrementAttempts(): bool
    {
        $this->attempts = ($this->attempts ?? 0) + 1;
        return $this->save();
    }

    public function setError(string $error): bool
    {
        $this->last_error = $error;
        return $this->save();
    }

    public static function available(): array
    {
        return static::query()
            ->whereNull('locked_at')
            ->where('available_at', '<=', current_time('mysql'))
            ->orderBy('available_at', 'asc')
            ->get();
    }

    public static function stale(int $minutes = 30): array
    {
        $threshold = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));

        return static::query()
            ->whereNotNull('locked_at')
            ->where('locked_at', '<', $threshold)
            ->get();
    }

    public static function forRun(int $runId): Collection
    {
        return static::where('run_id', $runId)->get();
    }
}
