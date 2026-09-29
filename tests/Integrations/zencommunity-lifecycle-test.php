<?php
// CLI only. Keep every test workflow, run, comment and ticket conversation.
if ( PHP_SAPI !== 'cli' ) { exit; }
$argv[1] = 'lifecycle';
require __DIR__ . '/zencommunity-workflow-realtest.php';
$option = 'zaplane_zenc_keep_lifecycle_20260924';
$state = (array) get_option( $option, [] );
$group_id = 19;
$ticket_id = 8;
$title = '[Keep / Paused] Post to ticket lifecycle reply note priority close reopen';
if ( ! $ticket::exists( $ticket_id ) ) {
	throw new RuntimeException( 'The dedicated QA ticket is unavailable.' );
}
function lifecycle_node( int $id, string $type, string $event, array $config ): array {
	return [
		'id' => (string) $id, 'type' => $type,
		'position' => [ 'x' => $id * 250, 'y' => 120 ],
		'data' => [ 'app' => 'zencommunity', 'event' => $event,
			'label' => $event, 'config' => $config ],
	];
}
if ( empty( $state['workflow_id'] ) ) {
	$created = zaplane_request( 'POST', '/zaplane/v1/workflows',
		[ 'title' => $title ] );
	$workflow_id = absint( $created['id'] ?? 0 );
	if ( ! $workflow_id ) { throw new RuntimeException( 'Could not create lifecycle workflow.' ); }
	$nodes = [
		lifecycle_node( 1, 'trigger', 'post_in_space', [ 'group_id' => $group_id ] ),
		lifecycle_node( 2, 'action', 'reply_ticket', [
			'ticket_id' => $ticket_id, 'user_id' => $admin,
			'content' => '[Zaplane lifecycle QA] A real automation reply; retain.' ] ),
		lifecycle_node( 3, 'action', 'note_ticket', [
			'ticket_id' => $ticket_id,
			'content' => '[Zaplane lifecycle QA] Retained internal note.' ] ),
		lifecycle_node( 4, 'action', 'change_ticket_priority', [
			'ticket_id' => $ticket_id, 'priority_id' => 16 ] ),
		lifecycle_node( 5, 'action', 'close_ticket', [ 'ticket_id' => $ticket_id ] ),
		lifecycle_node( 6, 'action', 'reopen_ticket', [ 'ticket_id' => $ticket_id ] ),
	];
	$edges = [];
	for ( $i = 1; $i < count( $nodes ); $i++ ) {
		$edges[] = [ 'id' => 'e' . $i, 'source' => (string) $i,
			'target' => (string) ( $i + 1 ), 'sourceHandle' => 'main' ];
	}
	zaplane_request( 'PUT', '/zaplane/v1/workflows/' . $workflow_id,
		[ 'nodes' => $nodes, 'edges' => $edges ] );
	$state['workflow_id'] = $workflow_id;
	update_option( $option, $state, false );
}
$wf = \Zaplane\Models\Workflow::find( (int) $state['workflow_id'] );
$wf->activate();
do_action( 'zaplane_workflow_updated', (int) $wf->id );
try {
	if ( empty( $state['feed_id'] ) ) {
		$state['feed_id'] = $feed::create( $group_id, [
			'type' => 'post', 'status' => 'published',
			'title' => '[Keep] Ticket lifecycle integration workflow',
			'content' => 'Real workflow: reply, internal note, priority, close, reopen. Preserve.' ], $admin );
		update_option( $option, $state, false );
	}
	report( 'LIFECYCLE_TEST_DATA', $state );
	$run = run_saved_workflow( (int) $wf->id );
	$t = $ticket::by_id( $ticket_id );
	global $wpdb;
	$conversations = $wpdb->get_results( $wpdb->prepare(
		"SELECT id,ticket_id,content,is_note FROM {$wpdb->prefix}zenc_ticket_conversations
		 WHERE ticket_id=%d AND content LIKE %s ORDER BY id DESC LIMIT 6",
		$ticket_id, '%[Zaplane lifecycle QA]%' ), ARRAY_A );
	report( 'LIFECYCLE_TICKET_READBACK', [
		'id' => $ticket_id, 'status' => $t['status'] ?? '',
		'priority_id' => $t['priority_id'] ?? null, 'conversations' => $conversations,
	] );
	if ( ( $t['status'] ?? '' ) !== 'open' || (int) ( $t['priority_id'] ?? 0 ) !== 16
		|| count( $conversations ) < 2 ) {
		throw new RuntimeException( 'Persisted ticket lifecycle state did not match.' );
	}
	report( 'LIFECYCLE_RESULT', 'PASS_RETAINED_PAUSED' );
} finally {
	$wf->pause();
	do_action( 'zaplane_workflow_updated', (int) $wf->id );
}

