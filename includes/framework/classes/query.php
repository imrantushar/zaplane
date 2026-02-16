<?php

namespace Zaplane\Framework\Classes;

use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;

if (!defined('ABSPATH')) {
    exit;
}

class Query
{
    public static function get_active_trigger_events(): array
    {
        try {
            $activeWorkflows = Workflow::active();
        } catch (\Exception $e) {
            // Tables might not exist yet during installation
            return [];
        }

        $events = [];

        foreach ($activeWorkflows as $workflow) {
            $version = $workflow->activeVersion();
            if (!$version) {
                continue;
            }

            $graph = $version->getGraph();
            foreach ($graph['nodes'] ?? [] as $node) {
                if (($node['type'] ?? '') === 'trigger' && !empty($node['data']['hook'])) {
                    $events[] = $node['data']['hook'];
                }
            }
        }
        return array_unique($events);
    }

    public static function get_active_workflows_for_event(string $event): array
    {
        try {
            $activeWorkflows = Workflow::active();
        } catch (\Exception $e) {
            // Tables might not exist yet during installation
            return [];
        }

        $out = [];

        foreach ($activeWorkflows as $workflow) {
            $version = $workflow->activeVersion();
            if (!$version) {
                continue;
            }

            $graph = $version->getGraph();
            foreach ($graph['nodes'] ?? [] as $node) {
                if (($node['type'] ?? '') === 'trigger' && ($node['data']['hook'] ?? '') === $event) {
                    $out[] = [
                        'workflow_version_hash' => $version->graph_hash,
                        'id' => $node['id'],
                        'app' => $node['data']['app'] ?? '',
                        'graph_node' => $node,
                    ];
                }
            }
        }

        return $out;
    }
}
