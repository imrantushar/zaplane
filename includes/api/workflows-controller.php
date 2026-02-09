<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;
use Zaplane\Models\Run;
use Zaplane\Utils\VariableExtractor;

if (!defined('ABSPATH')) exit;

class WorkflowsController extends WP_REST_Controller
{
    protected ?Container $container = null;

    public function __construct(?Container $container = null) {
        $this->container = $container;
    }

    public function register_routes()
    {
        $namespace = 'zaplane/v1';
        $rest_base = 'workflows';

        register_rest_route($namespace, '/' . $rest_base, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_workflow_items'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);

        register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_workflow_item'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);

        register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)/graph', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_graph'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);

        register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)/versions', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_versions'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);

        register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)/versions/(?P<version_id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_version'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);

        register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)/versions/(?P<version_id>\d+)/activate', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'activate_version'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);

        register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)/runs', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_runs'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);

        register_rest_route($namespace, '/condition-variables', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_condition_variables'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);
    }

    public function permissions_check()
    {
        return current_user_can('manage_options');
    }

    public function get_workflow_items()
    {
        $workflows = Workflow::orderBy('id', 'desc')->get();
        return rest_ensure_response($workflows->toArray());
    }

    public function get_workflow_item($request)
    {
        $workflow = Workflow::find((int) $request['id']);

        if (!$workflow) {
            return new WP_Error('not_found', 'Workflow not found', ['status' => 404]);
        }

        return rest_ensure_response($workflow->toArray());
    }

    public function create_item($request)
    {
        $workflow = Workflow::create([
            'user_id' => get_current_user_id(),
            'title' => sanitize_text_field($request['title']),
            'name' => sanitize_text_field($request['name']),
            'status' => 'draft',
        ]);

        return rest_ensure_response(['id' => $workflow->id]);
    }

    public function update_item($request)
    {
        $workflowId = (int) $request['id'];
        $graph = $request->get_json_params();

        if (!isset($graph['nodes']) || !isset($graph['edges'])) {
            return new WP_Error('invalid_graph', 'Invalid React Flow graph', ['status' => 400]);
        }

        WorkflowVersion::where('workflow_id', $workflowId)->update(['is_active' => 0]);

        $version = WorkflowVersion::create([
            'workflow_id' => $workflowId,
            'graph_json' => $graph,
            'graph_hash' => hash('sha256', wp_json_encode($graph)),
            'is_active' => 1,
        ]);

        return rest_ensure_response([
            'workflow_id' => $workflowId,
            'version_id' => $version->id,
        ]);
    }

    public function delete_item($request)
    {
        $workflow = Workflow::find((int) $request['id']);

        if ($workflow) {
            $workflow->delete();
        }

        return rest_ensure_response(['deleted' => true]);
    }

    public function get_graph($request)
    {
        $workflow = Workflow::find((int) $request['id']);

        if (!$workflow) {
            return new WP_Error('not_found', 'Workflow not found', ['status' => 404]);
        }

        $version = $workflow->activeVersion();
        $graph = $version ? $version->getGraph() : ['nodes' => [], 'edges' => []];

        // Get test outputs for each node
        $testOutputs = [];
        if ($version) {
            $nodeRuns = Run::latestTestNodeRuns($version->graph_hash);

            foreach ($nodeRuns as $nodeKey => $nodeRun) {
                $output = $nodeRun->getOutput();

                $outputData = $output['data'] ?? $output;

                $testOutputs[$nodeKey] = [
                    'node_run_id' => $nodeRun->id,
                    'run_id' => $nodeRun->run_id,
                    'output' => $outputData,
                    'variables' => VariableExtractor::extract($outputData),
                    'tested_at' => $nodeRun->finished_at,
                ];
            }
        }

        return rest_ensure_response([
            'workflow' => [
                'id' => $workflow->id,
                'title' => $workflow->title,
                'name' => $workflow->name,
                'status' => $workflow->status,
                'user_id' => $workflow->user_id,
            ],
            'version' => $version ? [
                'id' => $version->id,
                'hash' => $version->graph_hash,
                'created_at' => $version->created_at,
            ] : null,
            'graph' => $graph,
            'test_outputs' => $testOutputs,
        ]);
    }

    public function list_versions($request)
    {
        return rest_ensure_response(
            WorkflowVersion::where('workflow_id', (int) $request['id'])
                ->orderBy('id', 'desc')
                ->get()
                ->map(fn($v) => [
                    'id' => $v->id,
                    'graph_hash' => $v->graph_hash,
                    'is_active' => $v->is_active,
                    'created_at' => $v->created_at,
                ])
                ->toArray()
        );
    }

    public function get_version($request)
    {
        $version = WorkflowVersion::where('id', (int) $request['version_id'])
            ->where('workflow_id', (int) $request['id'])
            ->first();

        if (!$version) {
            return new WP_Error('not_found', 'Version not found', ['status' => 404]);
        }

        return rest_ensure_response([
            'id' => $version->id,
            'is_active' => $version->is_active,
            'graph' => $version->getGraph(),
        ]);
    }

    public function activate_version($request)
    {
        $workflowId = (int) $request['id'];
        $versionId = (int) $request['version_id'];

        $version = WorkflowVersion::where('id', $versionId)
            ->where('workflow_id', $workflowId)
            ->first();

        if (!$version) {
            return new WP_Error('invalid_version', 'Version not found', ['status' => 404]);
        }

        WorkflowVersion::where('workflow_id', $workflowId)->update(['is_active' => 0]);

        $version->is_active = true;
        $version->save();

        do_action('zaplane_workflow_updated', $workflowId);

        return rest_ensure_response([
            'workflow_id' => $workflowId,
            'active_version' => $versionId,
        ]);
    }

    public function get_runs($request)
    {
        $workflow = Workflow::find((int) $request['id']);

        if (!$workflow) {
            return rest_ensure_response([]);
        }

        $version = $workflow->activeVersion();

        if (!$version) {
            return rest_ensure_response([]);
        }

        return rest_ensure_response(
            Run::where('workflow_version_hash', $version->graph_hash)
                ->orderBy('id', 'desc')
                ->limit(100)
                ->get()
                ->map(fn($r) => [
                    'id' => $r->id,
                    'status' => $r->status,
                    'started_at' => $r->started_at,
                    'finished_at' => $r->finished_at,
                    'last_error' => $r->last_error,
                ])
                ->toArray()
        );
    }

    public function get_condition_variables($request)
    {
        $workflowHash = $request->get_param('workflow_hash');
        $targetNodeKey = (string) $request->get_param('target_node_key');

        if (empty($workflowHash) || empty($targetNodeKey)) {
            return new WP_Error('invalid_params', 'workflow_hash and target_node_key are required', ['status' => 400]);
        }

        $version = WorkflowVersion::where('graph_hash', $workflowHash)->first();

        if (!$version) {
            return new WP_Error('not_found', 'Workflow version not found', ['status' => 404]);
        }

        $graph = $version->getGraph();
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];

        $nodeMap = [];
        foreach ($nodes as $node) {
            $nodeMap[(string) $node['id']] = $node;
        }

        $previousNodeIds = $this->findPreviousNodes($targetNodeKey, $edges);
        $testNodeRuns = Run::latestTestNodeRuns($workflowHash);

        $variables = [];
        foreach ($previousNodeIds as $nodeId) {
            $node = $nodeMap[$nodeId] ?? null;
            if (!$node) continue;

            $nodeType = $node['type'] ?? '';
            if (!in_array($nodeType, ['action', 'trigger'])) continue;

            $nodeRun = $testNodeRuns[$nodeId] ?? null;
            $nodeLabel = $node['data']['label'] ?? $node['data']['event'] ?? "Node {$nodeId}";
            $nodeApp = $node['data']['app'] ?? 'unknown';

            if ($nodeRun) {
                $output = $nodeRun->getOutput();
                $outputData = $output['data'] ?? $output;

                if (!is_array($outputData)) {
                    $outputData = ['value' => $outputData];
                }

                $variables[] = [
                    'node_id' => $nodeId,
                    'node_label' => $nodeLabel,
                    'node_app' => $nodeApp,
                    'node_event' => $node['data']['event'] ?? null,
                    'has_test_data' => true,
                    'node_run_id' => $nodeRun->id,
                    'tested_at' => $nodeRun->finished_at,
                    'output' => $outputData,
                    'variables' => VariableExtractor::extract($outputData),
                ];
            } else {
                $variables[] = [
                    'node_id' => $nodeId,
                    'node_label' => $nodeLabel,
                    'node_app' => $nodeApp,
                    'node_event' => $node['data']['event'] ?? null,
                    'has_test_data' => false,
                    'node_run_id' => null,
                    'tested_at' => null,
                    'output' => null,
                    'variables' => [],
                ];
            }
        }

        return rest_ensure_response([
            'target_node_key' => $targetNodeKey,
            'previous_nodes' => $variables,
        ]);
    }

    private function findPreviousNodes(string $targetNodeId, array $edges): array
    {
        $previousNodes = [];
        $queue = [$targetNodeId];
        $visited = [$targetNodeId => true];

        while (!empty($queue)) {
            $currentId = array_shift($queue);

            foreach ($edges as $edge) {
                $target = (string) $edge['target'];
                $source = (string) $edge['source'];

                if ($target === $currentId && !isset($visited[$source])) {
                    $visited[$source] = true;
                    $previousNodes[] = $source;
                    $queue[] = $source;
                }
            }
        }

        return $previousNodes;
    }
}
