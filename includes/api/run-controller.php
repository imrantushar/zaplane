<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;
use Zaplane\Models\WorkflowVersion;

if (!defined('ABSPATH')) exit;

class RunController extends WP_REST_Controller
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function register_routes()
    {
        $ns = 'zaplane/v1';

        register_rest_route($ns, '/runs', [
            'methods' => 'GET',
            'callback' => [$this, 'list_runs'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/runs/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_run'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/runs/(?P<id>\d+)/replay', [
            'methods' => 'POST',
            'callback' => [$this, 'replay_run'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/runs/(?P<id>\d+)/stop', [
            'methods' => 'POST',
            'callback' => [$this, 'stop_run'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/node-runs/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_node_run'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/node-runs/(?P<id>\d+)/retry', [
            'methods' => 'POST',
            'callback' => [$this, 'retry_node'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/execute', [
            'methods' => 'POST',
            'callback' => [$this, 'execute_workflow'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/execute-node', [
            'methods' => 'POST',
            'callback' => [$this, 'execute_single_node'],
            'permission_callback' => [$this, 'permissions']
        ]);
    }

    public function permissions()
    {
        return current_user_can('manage_options');
    }

    /* ================= RUN LIST ================= */

    public function list_runs()
    {
        return Run::recent(100)
            ->map(fn($run) => [
                'id' => $run->id,
                'status' => $run->status,
                'started_at' => $run->started_at,
                'finished_at' => $run->finished_at,
            ])
            ->toArray();
    }

    /* ================= RUN VIEW ================= */

    public function get_run($req)
    {
        $id = (int) $req['id'];
        $run = Run::find($id);

        if (!$run) {
            return new WP_Error('not_found', 'Run not found', ['status' => 404]);
        }

        return [
            'run' => $run->toArray(),
            'nodes' => NodeRun::forRun($id)->toArray(),
        ];
    }

    /* ================= REPLAY ================= */

    public function replay_run($req)
    {
        $oldId = (int) $req['id'];
        $oldRun = Run::find($oldId);

        if (!$oldRun) {
            return new WP_Error('not_found', 'Run not found', ['status' => 404]);
        }

        $newRun = Run::create([
            'workflow_version_hash' => $oldRun->workflow_version_hash,
            'trigger_data' => $oldRun->trigger_data,
            'status' => 'running',
            'start_node_key' => $oldRun->start_node_key,
            'target_node_key' => $oldRun->target_node_key,
            'started_at' => current_time('mysql'),
        ]);

        $this->container->get('automation')->spawn_node_run(
            $newRun->id,
            $oldRun->start_node_key,
            $oldRun->trigger_data,
            null
        );

        return ['run_id' => $newRun->id];
    }

    /* ================= STOP ================= */

    public function stop_run($req)
    {
        $id = (int) $req['id'];
        $run = Run::find($id);

        if ($run) {
            $run->status = 'cancelled';
            $run->finished_at = current_time('mysql');
            $run->save();
        }

        // Cancel all pending node runs for this run
        NodeRun::where('run_id', $id)
            ->whereIn('status', ['pending', 'running', 'waiting'])
            ->update([
                'status' => 'cancelled',
                'finished_at' => current_time('mysql'),
            ]);

        return ['stopped' => true];
    }

    /* ================= NODE ================= */

    public function get_node_run($req)
    {
        $nodeRun = NodeRun::find((int) $req['id']);

        if (!$nodeRun) {
            return new WP_Error('not_found', 'Node run not found', ['status' => 404]);
        }

        return $nodeRun->toArray();
    }

    public function retry_node($req)
    {
        $id = (int) $req['id'];
        $nodeRun = NodeRun::find($id);

        if (!$nodeRun) {
            return new WP_Error('not_found', 'Node run not found', ['status' => 404]);
        }

        $nodeRun->status = 'pending';
        $nodeRun->started_at = null;
        $nodeRun->finished_at = null;
        $nodeRun->output_json = null;
        $nodeRun->save();

        as_enqueue_async_action('zaplane_execute_node_run', ['node_run_id' => $id], 'zaplane');

        return ['requeued' => true];
    }

    /* ================= EXECUTE FULL ================= */

    public function execute_workflow($req)
    {
        $workflowHash = $req['workflow_hash'];
        $data = $req['data'] ?? [];

        $version = WorkflowVersion::where('graph_hash', $workflowHash)->first();

        if (!$version) {
            return new WP_Error('not_found', 'Workflow version not found', ['status' => 404]);
        }

        $graph = $version->getGraph();
        $trigger = null;

        foreach ($graph['nodes'] as $node) {
            if ($node['type'] === 'trigger') {
                $trigger = $node;
                break;
            }
        }

        if (!$trigger) {
            return new WP_Error('no_trigger', 'No trigger node found', ['status' => 400]);
        }

        $run = Run::create([
            'workflow_version_hash' => $workflowHash,
            'status' => 'running',
            'trigger_data' => $data,
            'start_node_key' => $trigger['id'],
            'target_node_key' => null,
            'started_at' => current_time('mysql'),
        ]);

        $this->container->get('automation')->spawn_node_run(
            $run->id,
            $trigger['id'],
            $data,
            null
        );

        return ['run_id' => $run->id];
    }

    /* ================= EXECUTE SINGLE NODE ================= */

    public function execute_single_node($req)
    {
        $workflowHash = $req['workflow_hash'];
        $target = (string) $req['node_key'];
        $input = $req['input'] ?? [];

        $version = WorkflowVersion::where('graph_hash', $workflowHash)->first();

        if (!$version) {
            return new WP_Error('not_found', 'Workflow version not found', ['status' => 404]);
        }

        $graph = $version->getGraph();
        $trigger = null;

        foreach ($graph['nodes'] as $node) {
            if ($node['type'] === 'trigger') {
                $trigger = $node;
                break;
            }
        }

        if (!$trigger) {
            return new WP_Error('no_trigger', 'No trigger node found', ['status' => 400]);
        }

        $run = Run::create([
            'workflow_version_hash' => $workflowHash,
            'status' => 'running',
            'trigger_data' => $input,
            'start_node_key' => $trigger['id'],
            'target_node_key' => $target,
            'started_at' => current_time('mysql'),
        ]);

        $this->container->get('automation')->spawn_node_run(
            $run->id,
            $trigger['id'],
            $input,
            null
        );

        return [
            'run_id' => $run->id,
            'target_node' => $target,
        ];
    }
}
