<?php

namespace Zaplane\Models;

use Zaplane\Framework\Database\ORM\Model;

if (!defined('ABSPATH')) exit;

class Workflow extends Model
{
    protected static string $table = 'workflows';

    protected static array $fillable = [
        'user_id',
        'title',
        'name',
        'status',
    ];

    protected static array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
    ];

    public function versions(): array
    {
        return WorkflowVersion::where('workflow_id', $this->id)->orderBy('id', 'desc')->get();
    }

    public function activeVersion(): ?WorkflowVersion
    {
        return WorkflowVersion::where('workflow_id', $this->id)
            ->where('is_active', 1)
            ->first();
    }

    public function runs(): array
    {
        $version = $this->activeVersion();
        if (!$version) {
            return [];
        }
        return Run::where('workflow_version_hash', $version->graph_hash)
            ->orderBy('id', 'desc')
            ->get();
    }

    public function activate(): bool
    {
        $this->status = 'active';
        return $this->save();
    }

    public function pause(): bool
    {
        $this->status = 'paused';
        return $this->save();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public static function forUser(int $userId): array
    {
        return static::where('user_id', $userId)->orderBy('id', 'desc')->get();
    }

    public static function active(): array
    {
        return static::where('status', 'active')->get();
    }
}
