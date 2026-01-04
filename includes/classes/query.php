<?php
namespace Zaplane\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Query {  

    public static function get_active_trigger_events(): array {
        global $wpdb;

        $rows = $wpdb->get_results("
            SELECT wv.graph_json
            FROM {$wpdb->prefix}zaplane_workflows w
            JOIN {$wpdb->prefix}zaplane_workflow_versions wv 
            ON w.id = wv.workflow_id
            WHERE w.status='active' AND wv.is_active=1
        ", ARRAY_A);

        $events = [];
        foreach ($rows as $r) {
            $g = json_decode($r['graph_json'], true);
            foreach ($g['nodes'] ?? [] as $n) {
                if (($n['type'] ?? '') === 'trigger' && !empty($n['data']['event'])) {
                    $events[] = $n['data']['event'];
                }
            }
        }

        return array_unique($events);
    }

    public static function get_active_workflows_for_event(string $event): array {
        global $wpdb;

        $rows = $wpdb->get_results("
            SELECT w.id,w.user_id,wv.graph_json,wv.graph_hash
            FROM {$wpdb->prefix}zaplane_workflows w
            JOIN {$wpdb->prefix}zaplane_workflow_versions wv 
            ON w.id=wv.workflow_id
            WHERE w.status='active' AND wv.is_active=1
        ", ARRAY_A);

        $out = [];
        foreach ($rows as $r) {
            $g = json_decode($r['graph_json'], true);
            foreach ($g['nodes'] ?? [] as $n) {
                if (($n['type'] ?? '') === 'trigger' && ($n['data']['event'] ?? '') === $event) {
                    $out[] = [
                        'workflow_version_hash' => $r['graph_hash'],
                        'id' => $n['id'],
                        'app' => $n['data']['app'] ?? '',
                        'graph_node' => $n
                    ];
                }
            }
        }

        return $out;
    }
}