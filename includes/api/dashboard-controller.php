<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Database\ORM\DB;
use Zaplane\Models\Workflow;

if (!defined('ABSPATH')) exit;

class DashboardController extends WP_REST_Controller
{
    protected ?Container $container = null;

    public function __construct(?Container $container = null)
    {
        $this->container = $container;
    }

    public function register_routes()
    {
        register_rest_route('zaplane/v1', '/dashboard/top-workflows', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_top_workflows'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);
    }

    public function permissions_check()
    {
        return current_user_can('manage_options');
    }

    public function get_top_workflows()
    {
        $results = DB::table('runs')
            ->select('workflow_version_id')
            ->selectRaw('COUNT(*) as total_runs')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as success_runs")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_runs")
            ->groupBy('workflow_version_id')
            ->orderBy('total_runs', 'desc')
            ->limit(10)
            ->get();

        $topWorkflows = [];

        foreach ($results as $row) {
            $versionId = (int) $row['workflow_version_id'];

            $version = \Zaplane\Models\WorkflowVersion::find($versionId);
            if (!$version) {
                continue;
            }

            $workflow = Workflow::find($version->workflow_id);
            if (!$workflow) {
                continue;
            }

            $topWorkflows[] = [
                'workflow_id' => $workflow->id,
                'title' => $workflow->title,
                'status' => $workflow->status,
                'total_runs' => (int) $row['total_runs'],
                'success_runs' => (int) $row['success_runs'],
                'failed_runs' => (int) $row['failed_runs'],
            ];
        }

        return rest_ensure_response([
            'top_workflows' => $topWorkflows,
        ]);
    }
}
