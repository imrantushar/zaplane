<?php

namespace Zaplane\Models;

use Zaplane\Database\ORM\Model;

if (!defined('ABSPATH')) exit;

class WorkflowVersion extends Model
{
    protected static string $table = 'workflow_versions';

    protected static array $fillable = [
        'workflow_id',
        'graph_json',
        'graph_hash',
        'is_active',
    ];

    protected static array $casts = [
        'id' => 'integer',
        'workflow_id' => 'integer',
        'is_active' => 'boolean',
        'graph_json' => 'json',
    ];

    protected static bool $timestamps = false;
    protected static string $createdAt = 'created_at';

    public function workflow(): ?Workflow
    {
        return Workflow::find($this->workflow_id);
    }

    public function runs(): array
    {
        return Run::where('workflow_version_hash', $this->graph_hash)
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getGraph(): array
    {
        return $this->graph_json ?? ['nodes' => [], 'edges' => []];
    }

    public function getNodes(): array
    {
        $graph = $this->getGraph();
        return $graph['nodes'] ?? [];
    }

    public function getEdges(): array
    {
        $graph = $this->getGraph();
        return $graph['edges'] ?? [];
    }

    public function activate(): bool
    {
        global $wpdb;

        $wpdb->update(
            static::getTable(),
            ['is_active' => 0],
            ['workflow_id' => $this->workflow_id]
        );

        $this->is_active = true;
        return $this->save();
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public static function createFromGraph(int $workflowId, array $graph): self
    {
        $json = json_encode($graph);
        $hash = hash('sha256', $json);

        return static::create([
            'workflow_id' => $workflowId,
            'graph_json' => $json,
            'graph_hash' => $hash,
            'is_active' => true,
        ]);
    }
}
