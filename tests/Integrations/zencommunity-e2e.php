<?php
// Include from a temporary localhost-only WP bootstrap; do not expose directly.
if ( ! defined( 'ZC_E2E_ALLOW' ) || ! defined( 'ABSPATH' ) ) { exit; }
header( 'Content-Type: text/plain; charset=utf-8' );
use ZenCommunity\Database\Models\Group;
use ZenCommunity\Database\Models\Feed;
use Zaplane\Models\Workflow;
use Zaplane\Models\Run;

function zcqa( string $name, $value ): void {
	echo $name . '=' . ( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . PHP_EOL;
}
function zcqa_rest( string $method, string $route, array $data = [] ): array {
	$req = new WP_REST_Request( $method, $route );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( wp_json_encode( $data ) );
	$res = rest_do_request( $req );
	$out = $res->get_data();
	if ( $res->get_status() >= 400 ) {
		throw new RuntimeException( $route . ' HTTP ' . $res->get_status() . ': ' . wp_json_encode( $out ) );
	}
	return is_array( $out ) ? $out : [];
}
function zcqa_node( int $id, string $kind, string $app, string $event, array $config = [] ): array {
	return [ 'id' => $id, 'type' => $kind,
		'position' => [ 'x' => 80 + 300 * ( $id - 1 ), 'y' => 120 ],
		'data' => [ 'app' => $app, 'event' => $event,
			'label' => ucwords( str_replace( '_', ' ', $event ) ), 'config' => $config ] ];
}
function zcqa_workflow( string $title, array $nodes ): int {
	$existing = Workflow::where( 'title', $title )->first();
	if ( $existing ) { return (int) $existing->id; }
	$created = zcqa_rest( 'POST', '/zaplane/v1/workflows', [ 'title' => $title ] );
	$id = absint( $created['id'] ?? 0 );
	if ( ! $id ) { throw new RuntimeException( 'Workflow was not created.' ); }
	$edges = [];
	for ( $i = 1; $i < count( $nodes ); $i++ ) {
		$edges[] = [ 'id' => 'e' . $i . '-' . ( $i + 1 ), 'source' => (string) $i,
			'target' => (string) ( $i + 1 ), 'sourceHandle' => 'main' ];
	}
	zcqa_rest( 'POST', "/zaplane/v1/workflows/{$id}", [
		'nodes' => $nodes, 'edges' => $edges, 'integration_icons' => [ 'zencommunity' ],
	] );
	$workflow = Workflow::find( $id );
	$workflow->activate();
	do_action( 'zaplane_workflow_updated', $id );
	return $id;
}
function zcqa_drain( array $workflow_ids, int $max = 35 ): void {
	wp_set_current_user( 0 ); // Simulate Action Scheduler: only trusted trigger identity is stored.
	for ( $iteration = 0; $iteration < $max; $iteration++ ) {
		$pending = 0;
		foreach ( $workflow_ids as $workflow_id ) {
			$runs = Run::where( 'workflow_id', $workflow_id )->orderBy( 'id', 'desc' )->limit( 8 )->get();
			foreach ( $runs as $run ) {
				foreach ( $run->nodeRuns() as $node ) {
					if ( 'pending' !== $node->status ) { continue; }
					$pending++;
					do_action( 'zaplane_execute_node_run', (int) $node->id );
				}
			}
		}
		if ( 0 === $pending ) { break; }
	}
	foreach ( $workflow_ids as $workflow_id ) {
		$runs = Run::where( 'workflow_id', $workflow_id )->orderBy( 'id', 'desc' )->limit( 3 )->get();
		foreach ( $runs as $run ) {
			zcqa( 'RUN', [ 'workflow_id' => $workflow_id, 'run_id' => $run->id, 'status' => Run::find( $run->id )->status ] );
			foreach ( $run->nodeRuns() as $node ) {
				$latest = \Zaplane\Models\NodeRun::find( $node->id );
				$out = $latest->getOutput();
				zcqa( 'NODE', [ 'run_id' => $run->id, 'key' => $node->node_key,
					'status' => $latest->status, 'output' => $out ] );
			}
		}
	}
}
try {
	$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => [ 'ID' ] ] );
	$admin = absint( $admins[0]->ID ?? 0 );
	if ( ! $admin ) { throw new RuntimeException( 'Administrator not found.' ); }
	wp_set_current_user( $admin );
	zcqa( 'ACTOR_ID', $admin );
	$cat = Group::ins()->qb()->where_null( 's.category_id' )->first();
	if ( ! $cat ) {
		$category_id = Group::create( [ 'name' => '[Zaplane QA] Test Category',
			'slug' => 'zaplane-zencommunity-qa-cat-0924' ] );
	} else { $category_id = absint( $cat['id'] ?? 0 ); }
	$slug = 'zaplane-zencommunity-qa-space-0924';
	$space = Group::ins()->qb()->where( 's.slug', '=', $slug )->first();
	$group_id = $space ? absint( $space['id'] ) :
		Group::create( [ 'name' => '[Zaplane QA] ZenCommunity Workflow Test Space',
			'slug' => $slug, 'privacy' => 'public', 'status' => 'published' ], $category_id );
	zcqa( 'TEST_SPACE_ID', $group_id );
	$manual = zcqa_workflow( '[Zaplane QA] ZenCommunity Core + Pro end-to-end 2026-09-24', [
		zcqa_node( 1, 'trigger', 'manual', 'run_manually' ),
		zcqa_node( 2, 'action', 'zencommunity', 'create_post', [
			'group_id' => $group_id, 'user_id' => $admin,
			'content' => 'Zaplane QA: real workflow post (preserve this test data).' ] ),
		zcqa_node( 3, 'action', 'zencommunity', 'add_comment', [
			'feed_id' => '{{2.feed_id}}', 'user_id' => $admin,
			'content' => 'Zaplane QA: real comment from previous node.' ] ),
		zcqa_node( 4, 'action', 'zencommunity', 'create_ticket', [
			'user_id' => $admin, 'title' => '[Zaplane QA] Live support flow',
			'content' => 'Zaplane QA: ticket created by a real automation.' ] ),
		zcqa_node( 5, 'action', 'zencommunity', 'reply_ticket', [
			'ticket_id' => '{{4.ticket_id}}', 'user_id' => $admin,
			'content' => 'Zaplane QA: ticket response by automation.' ] ),
		zcqa_node( 6, 'action', 'zencommunity', 'close_ticket',
			[ 'ticket_id' => '{{4.ticket_id}}' ] ),
		zcqa_node( 7, 'action', 'zencommunity', 'reopen_ticket',
			[ 'ticket_id' => '{{4.ticket_id}}' ] ),
	] );
	$automatic = zcqa_workflow( '[Zaplane QA] Specific space post to Pro ticket 2026-09-24', [
		zcqa_node( 1, 'trigger', 'zencommunity', 'post_in_space', [ 'group_id' => $group_id ] ),
		zcqa_node( 2, 'action', 'zencommunity', 'create_ticket', [
			'user_id' => '{{1.user_id}}', 'title' => '[Zaplane QA] Ticket from community post',
			'content' => 'Zaplane QA: created automatically when a post is published in the test space.' ] ),
	] );
	zcqa( 'WORKFLOW_IDS', [ $manual, $automatic ] );
	$manual_run = zcqa_rest( 'POST', "/zaplane/v1/workflows/{$manual}/trigger", [
		'data' => [ 'source' => 'human-style-e2e' ] ] );
	zcqa( 'MANUAL_RUN', $manual_run );
	$post_id = Feed::create( $group_id, [
		'type' => 'post', 'status' => 'published',
		'content' => 'Zaplane QA: actual post event tests automatic workflow. Please preserve.' ], $admin );
	zcqa( 'POST_TRIGGER_ID', $post_id );
	zcqa_drain( [ $manual, $automatic ] );
} catch ( Throwable $e ) {
	zcqa( 'E2E_ERROR', $e->getMessage() );
	zcqa( 'E2E_LOCATION', basename( $e->getFile() ) . ':' . $e->getLine() );
}

