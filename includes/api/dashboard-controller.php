<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Database\ORM\DB;
use Zaplane\Models\Workflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DashboardController extends WP_REST_Controller {

	protected ?Container $container = null;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
	}

	public function register_routes() {
		register_rest_route( 'zaplane/v1', '/dashboard/summary', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_summary' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( 'zaplane/v1', '/dashboard/top-workflows', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_top_workflows' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
	}

	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	public function get_summary() {
		$totalWorkflows  = Workflow::count();
		$activeWorkflows = Workflow::where( 'status', 'active' )->count();
		$totalExecutions = DB::table( 'runs' )->count();

		$monthlyExecutions = $this->get_monthly_executions();

		return rest_ensure_response( [
			'total_workflows'   => (int) $totalWorkflows,
			'active_workflows'  => (int) $activeWorkflows,
			'total_executions'  => (int) $totalExecutions,
			'monthly_executions' => $monthlyExecutions,
		] );
	}

	private function get_monthly_executions(): array {
		$year = (int) gmdate( 'Y' );

		$rows = DB::table( 'runs' )
			->selectRaw( 'MONTH(started_at) as month_num, COUNT(*) as runs' )
			->whereRaw( 'YEAR(started_at) = %d', [ $year ] )
			->groupBy( 'month_num' )
			->orderBy( 'month_num', 'asc' )
			->get();

		// Index results by month number for quick lookup.
		$indexed = [];
		foreach ( $rows as $row ) {
			$indexed[ (int) $row['month_num'] ] = (int) $row['runs'];
		}

		$months = [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ];
		$result = [];

		foreach ( $months as $i => $label ) {
			$result[] = [
				'month' => $label,
				'runs'  => $indexed[ $i + 1 ] ?? 0,
			];
		}

		return $result;
	}

	public function get_top_workflows() {
		$results = DB::table( 'runs' )
			->select( 'workflow_version_id' )
			->selectRaw( 'COUNT(*) as total_runs' )
			->selectRaw( "SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as success_runs" )
			->selectRaw( "SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_runs" )
			->groupBy( 'workflow_version_id' )
			->orderBy( 'total_runs', 'desc' )
			->limit( 10 )
			->get();

		$topWorkflows = [];

		foreach ( $results as $row ) {
			$versionId = (int) $row['workflow_version_id'];

			$version = \Zaplane\Models\WorkflowVersion::find( $versionId );
			if ( ! $version ) {
				continue;
			}

			$workflow = Workflow::find( $version->workflow_id );
			if ( ! $workflow ) {
				continue;
			}

			$topWorkflows[] = [
				'workflow_id'  => $workflow->id,
				'title'        => $workflow->title,
				'status'       => $workflow->status,
				'total_runs'   => (int) $row['total_runs'],
				'success_runs' => (int) $row['success_runs'],
				'failed_runs'  => (int) $row['failed_runs'],
			];
		}//end foreach

		return rest_ensure_response( [
			'top_workflows' => $topWorkflows,
		] );
	}
}
