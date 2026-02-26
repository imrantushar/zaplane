<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;
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
                'args' => [
                    'page' => [
                        'type' => 'integer',
                        'default' => 1,
                        'minimum' => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'per_page' => [
                        'type' => 'integer',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100,
                        'sanitize_callback' => 'absint',
                    ],
                ],
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

    public function get_workflow_items($request)
    {
        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = max(1, min(100, (int) ($request->get_param('per_page') ?? 20)));

        $total = Workflow::count();
        $workflows = Workflow::orderBy('id', 'desc')
            ->forPage($page, $perPage)
            ->get();

        $data = [];
        foreach ($workflows as $workflow) {
            $item = $workflow->toArray();

            $version = $workflow->activeVersion();
            if ($version) {
                $successRuns = Run::where('workflow_version_hash', $version->graph_hash)
                    ->where('status', 'completed')
                    ->count();
                $failedRuns = Run::where('workflow_version_hash', $version->graph_hash)
                    ->where('status', 'failed')
                    ->count();
            } else {
                $successRuns = 0;
                $failedRuns = 0;
            }

            $item['success_runs'] = $successRuns;
            $item['failed_runs'] = $failedRuns;
            $data[] = $item;
        }

        return rest_ensure_response([
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
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
        $graph      = $request->get_json_params();

        if (!isset($graph['nodes']) || !isset($graph['edges'])) {
            return new WP_Error('invalid_graph', 'Invalid React Flow graph', ['status' => 400]);
        }

        $hash    = hash('sha256', wp_json_encode($graph));
        $current = WorkflowVersion::where('workflow_id', $workflowId)->where('is_active', 1)->first();

        if ($current) {
            if ($current->graph_hash === $hash) {
                return rest_ensure_response([
                    'workflow_id' => $workflowId,
                    'version_id'  => $current->id,
                ]);
            }

            $current->graph_json = $graph;
            $current->graph_hash = $hash;
            $current->save();

            return rest_ensure_response([
                'workflow_id' => $workflowId,
                'version_id'  => $current->id,
            ]);
        }

        // No active version yet — first save for this workflow
        $version = WorkflowVersion::create([
            'workflow_id' => $workflowId,
            'graph_json'  => $graph,
            'graph_hash'  => $hash,
            'is_active'   => 1,
        ]);

        return rest_ensure_response([
            'workflow_id' => $workflowId,
            'version_id'  => $version->id,
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

                if (!is_array($output)) {
                    $output = ['value' => $output];
                }

                $testOutputs[$nodeKey] = [
                    'node_run_id' => $nodeRun->id,
                    'run_id' => $nodeRun->run_id,
                    'output' => $output,
                    'variables' => VariableExtractor::extract($output),
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
                'layout' => $workflow->layout,
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
        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = max(1, min(100, (int) ($request->get_param('per_page') ?? 20)));
        $workflowId = (int) $request['id'];

        $total = WorkflowVersion::where('workflow_id', $workflowId)->count();
        $versions = WorkflowVersion::where('workflow_id', $workflowId)
            ->orderBy('id', 'desc')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn($v) => [
                'id' => $v->id,
                'graph_hash' => $v->graph_hash,
                'is_active' => $v->is_active,
                'created_at' => $v->created_at,
            ])
            ->toArray();

        return rest_ensure_response([
            'data' => $versions,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
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
            'version_id' => $versionId,
            'is_active' => $version->is_active,
        ]);
    }

    public function get_runs($request)
    {
        $workflow = Workflow::find((int) $request['id']);

        if (!$workflow) {
            return rest_ensure_response([
                'data' => [],
                'pagination' => ['page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 0],
            ]);
        }

        $version = $workflow->activeVersion();

        if (!$version) {
            return rest_ensure_response([
                'data' => [],
                'pagination' => ['page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 0],
            ]);
        }

        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = max(1, min(100, (int) ($request->get_param('per_page') ?? 20)));

        $total = Run::where('workflow_version_hash', $version->graph_hash)->count();
        $runs = Run::where('workflow_version_hash', $version->graph_hash)
            ->orderBy('id', 'desc')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'status' => $r->status,
                'started_at' => $r->started_at,
                'finished_at' => $r->finished_at,
                'last_error' => $r->last_error,
                'node_runs_count' => NodeRun::where('run_id', $r->id)->count(),
            ])
            ->toArray();

        return rest_ensure_response([
            'data' => $runs,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function get_condition_variables($request)
    {
        $targetNodeKey = (int) $request->get_param('target_node_key');
        $workflowId    = (int) $request->get_param('workflow_id');
        $workflowHash  = $request->get_param('workflow_hash');

        if (!$targetNodeKey) {
            return new WP_Error('invalid_params', 'target_node_key is required', ['status' => 400]);
        }

        // Resolve graph structure — prefer inline graph from request body
        $inlineGraph = $request->get_json_params();

        if ($workflowId && $inlineGraph && isset($inlineGraph['nodes'])) {
            $graph = $inlineGraph;
        } elseif ($workflowId) {
            $version = WorkflowVersion::where('workflow_id', $workflowId)->where('is_active', 1)->first();
            if (!$version) {
                return rest_ensure_response(['status' => 'success', 'code' => 'SUCCESS', 'data' => []]);
            }
            $graph = $version->getGraph();
        } elseif ($workflowHash) {
            $version = WorkflowVersion::where('graph_hash', $workflowHash)->first();
            if (!$version) {
                return new WP_Error('not_found', 'Workflow version not found', ['status' => 404]);
            }
            $graph = $version->getGraph();
        } else {
            return new WP_Error('invalid_params', 'workflow_id or workflow_hash is required', ['status' => 400]);
        }

        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];

        $nodeMap = [];
        foreach ($nodes as $node) {
            $nodeMap[(int) $node['id']] = $node;
        }

        $previousNodeIds = $this->findPreviousNodes($targetNodeKey, $edges);

        // Resolve node outputs — prefer workflow_id lookup for unsaved workflows
        $nodeOutputs = $workflowId
            ? Run::latestTestNodeRunsByWorkflow($workflowId)
            : Run::latestNodeOutputs($workflowHash);

        $data = [];
        foreach ($previousNodeIds as $nodeId) {
            $node = $nodeMap[$nodeId] ?? null;
            if (!$node) continue;

            $nodeType = $node['type'] ?? '';
            if (!in_array($nodeType, ['action', 'trigger', 'condition', 'filter'])) continue;

            $nodeRun = $nodeOutputs[$nodeId] ?? null;

            if ($nodeRun) {
                $output = $nodeRun->getOutput();

                if (!is_array($output)) {
                    $output = ['value' => $output];
                }

                $data[] = [
                    'node_id' => $nodeId,
                    'node_name' => $node['data']['name'] ?? '',
                    'node_event' => $node['data']['event'] ?? '',
                    'variables' => VariableExtractor::extract($output),
                ];
            } else {
                $data[] = [
                    'node_id' => $nodeId,
                    'node_name' => $node['data']['name'] ?? '',
                    'node_event' => $node['data']['event'] ?? '',
                    'variables' => [],
                ];
            }
        }

        return rest_ensure_response([
            'status' => 'success',
            'code' => 'SUCCESS',
            'data' => $data,
        ]);
    }

    private function findPreviousNodes(int $targetNodeId, array $edges): array
    {
        $previousNodes = [];
        $queue = [$targetNodeId];
        $visited = [$targetNodeId => true];

        while (!empty($queue)) {
            $currentId = array_shift($queue);

            foreach ($edges as $edge) {
                $target = (int) $edge['target'];
                $source = (int) $edge['source'];

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
