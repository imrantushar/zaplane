<?php
// Local CLI-only integration test. All created workflows and community records are retained.
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/zencommunity-cli-bootstrap.php';
$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
if ( ! $admins ) { throw new RuntimeException( 'No administrator found.' ); }
$admin = (int) $admins[0];
wp_set_current_user( $admin );
$c = \Zaplane\Integrations\Zencommunity::class;
$ticket = \ZenCommunityPro\Addons\TicketingSystem\Database\Models\Ticket::class;
$group = \ZenCommunity\Database\Models\Group::class;
$feed = \ZenCommunity\Database\Models\Feed::class;
$automation = \Zaplane\Framework\Core\Automation::get_instance();
$command = $argv[1] ?? 'inspect';
function report( string $name, $value ): void {
	echo $name . '=' . wp_json_encode( $value, JSON_UNESCAPED_SLASHES ) . PHP_EOL;
}
report( 'TEST_MODE', $command );
report( 'ADMIN_ID', $admin );
report( 'PRO_TICKET_CLASS', class_exists( $ticket ) );
report( 'MESSAGING_CLASS', class_exists( \ZenCommunityPro\Addons\Messaging\Database\Models\PrivateMessage::class ) );
report( 'TRIGGER_COUNT', count( $c::get_triggers() ) );
report( 'ACTION_COUNT', count( $c::get_actions() ) );
if ( 'inspect' === $command ) {
	global $wpdb;
	report( 'ADMIN_PROFILE', \ZenCommunity\Database\Models\Profile::exists( $admin ) );
 global $zencommunity_settings;
 report( 'MEMBER_REGISTRATION_ENABLED', $zencommunity_settings->allow_member_registration ?? null );
	report( 'GROUPS', $wpdb->get_results( "SELECT id,name,category_id,privacy FROM {$wpdb->prefix}zenc_groups ORDER BY id DESC LIMIT 6", ARRAY_A ) );
	report( 'TICKET_COUNT', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}zenc_tickets" ) );
 report( 'TEST_USERS', $wpdb->get_results( "SELECT user_id,username,status FROM {$wpdb->prefix}zenc_profiles WHERE username LIKE '%test%' ORDER BY user_id DESC LIMIT 12", ARRAY_A ) );
 report( 'ADMIN_GROUPS', $wpdb->get_results( $wpdb->prepare("SELECT group_id,role,status FROM {$wpdb->prefix}zenc_group_members WHERE user_id=%d ORDER BY group_id DESC LIMIT 8",$admin), ARRAY_A ) );
 report( 'TICKET_LABELS', $wpdb->get_results( "SELECT id,type,name FROM {$wpdb->prefix}zenc_ticket_labels ORDER BY id DESC LIMIT 20", ARRAY_A ) );
 report( 'TICKET_PRODUCTS', $wpdb->get_results( "SELECT id,name FROM {$wpdb->prefix}zenc_ticket_products ORDER BY id DESC LIMIT 12", ARRAY_A ) );
	exit;
}


function zaplane_request( string $method, string $path, array $params ): array {
	$request = new WP_REST_Request( $method, $path );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( $params ) );
	$response = rest_do_request( $request );
	if ( $response->get_status() >= 400 ) {
		throw new RuntimeException( wp_json_encode( $response->get_data() ) );
	}
	return (array) $response->get_data();
}

function make_workflow( string $title, string $event, string $hook, array $tconf, string $action, array $aconf ): int {
	$created = zaplane_request( 'POST', '/zaplane/v1/workflows', [ 'title' => $title ] );
	$id = absint( $created['id'] ?? 0 );
	if ( ! $id ) { throw new RuntimeException( 'Create workflow failed.' ); }
	$graph = [
		'nodes' => [
			[ 'id' => '1', 'type' => 'trigger', 'position' => [ 'x' => 0, 'y' => 0 ],
				'data' => [ 'app' => 'zencommunity', 'event' => $event, 'label' => $event,
					'hook' => $hook, 'config' => $tconf ] ],
			[ 'id' => '2', 'type' => 'action', 'position' => [ 'x' => 400, 'y' => 0 ],
				'data' => [ 'app' => 'zencommunity', 'event' => $action, 'label' => $action,
					'config' => $aconf ] ],
		],
		'edges' => [ [ 'id' => 'e1-2', 'source' => '1', 'target' => '2', 'sourceHandle' => 'main' ] ],
	];
	$saved = zaplane_request( 'PUT', '/zaplane/v1/workflows/' . $id, $graph );
	$wf = \Zaplane\Models\Workflow::find( $id );
	if ( ! $wf || ! $wf->activate() ) { throw new RuntimeException( 'Activation failed.' ); }
	do_action( 'zaplane_workflow_updated', $id );
	report( 'WORKFLOW_CREATED', [ 'id' => $id, 'version' => $saved['version_id'] ?? 0,
		'trigger' => $event, 'action' => $action ] );
	return $id;
}

function run_saved_workflow( int $id ): array {
	global $wpdb;
	$runs = $wpdb->prefix . 'zaplane_runs';
	$node_runs = $wpdb->prefix . 'zaplane_node_runs';
	$run_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM {$runs} WHERE workflow_id=%d ORDER BY id DESC LIMIT 1", $id ) );
	if ( ! $run_id ) { throw new RuntimeException( 'No trigger run for workflow ' . $id ); }
	for ( $step = 0; $step < 8; ++$step ) {
		$pending = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$node_runs} WHERE run_id=%d AND status='pending' ORDER BY id", $run_id ) );
		if ( ! $pending ) { break; }
		foreach ( $pending as $node_id ) {
			\Zaplane\Framework\Core\Automation::get_instance()->dispatch_node_run( (int) $node_id );
		}
	}
	$run = $wpdb->get_row( $wpdb->prepare(
		"SELECT id,status,workflow_id FROM {$runs} WHERE id=%d", $run_id ), ARRAY_A );
	$nodes = $wpdb->get_results( $wpdb->prepare(
		"SELECT id,node_key,status,output_json FROM {$node_runs} WHERE run_id=%d ORDER BY id", $run_id ), ARRAY_A );
	foreach ( $nodes as &$node ) { $node['output_json'] = json_decode( $node['output_json'] ?: '{}', true ); }
	unset( $node );
	report( 'REAL_RUN', [ 'run' => $run, 'nodes' => $nodes ] );
	if ( count( $nodes ) < 2 ) { throw new RuntimeException( 'Action node did not execute.' ); }
	foreach ( $nodes as $node ) {
		if ( 'completed' !== $node['status'] || ( 2 === (int) $node['node_key']
			&& empty( $node['output_json']['data']['success'] ) ) ) {
			throw new RuntimeException( 'Real workflow failed at node ' . $node['node_key'] );
		}
	}
	return [ 'run' => $run, 'nodes' => $nodes ];
}

if ( 'post' === $command ) {
	$state = (array) get_option( 'zaplane_zenc_keep_post_20260924', [] );
	$group_id = 19; // Existing dedicated "Zaplane Test Space".
	$reply_text = '[Zaplane integration test] Automatic comment from a real post trigger. Keep this record.';
	if ( empty( $state['workflow_id'] ) ) {
		$state['workflow_id'] = make_workflow(
			'[Keep] ZenCommunity post created in space → automation comment',
			'post_in_space', 'zencommunity/feed/created', [ 'group_id' => $group_id ],
			'add_comment', [ 'feed_id' => '{{trigger.feed_id}}', 'user_id' => $admin, 'content' => $reply_text ] );
		update_option( 'zaplane_zenc_keep_post_20260924', $state, false );
	}
	if ( empty( $state['feed_id'] ) ) {
		$state['feed_id'] = $feed::create( $group_id, [
			'title' => '[Keep] Zaplane / ZenCommunity real workflow post 2026-09-24',
			'content' => 'Human-style integration test: published post in a space; automation should add a comment.',
			'type' => 'post', 'status' => 'published',
		], $admin );
		update_option( 'zaplane_zenc_keep_post_20260924', $state, false );
	}
	report( 'POST_TEST_RECORD', $state );
	run_saved_workflow( (int) $state['workflow_id'] );
	global $wpdb;
	$comments = $wpdb->get_results( $wpdb->prepare(
		"SELECT id,feed_id,user_id,content FROM {$wpdb->prefix}zenc_comments WHERE feed_id=%d ORDER BY id DESC LIMIT 4",
		(int) $state['feed_id'] ), ARRAY_A );
	report( 'SAVED_COMMENTS', $comments );
	if ( ! array_filter( $comments, static fn($c) => str_contains( $c['content'], '[Zaplane integration test]' ) ) ) {
		throw new RuntimeException( 'Comment not found in ZenCommunity database.' );
	}
	report( 'POST_WORKFLOW_RESULT', 'PASS_RETAINED' );
	exit;
}

if ( 'ticket' === $command ) {
	$state = (array) get_option( 'zaplane_zenc_keep_ticket_20260924', [] );
	$reply_text = '[Zaplane integration test] Automatic support reply from a real ticket-created event. Keep this record.';
	if ( empty( $state['workflow_id'] ) ) {
		$state['workflow_id'] = make_workflow(
			'[Keep] ZenCommunity Pro ticket created → reply to ticket',
			'ticket_created', 'zencommunity/ticket/created', [],
			'reply_ticket', [ 'ticket_id' => '{{trigger.ticket_id}}',
				'user_id' => $admin, 'content' => $reply_text ] );
		update_option( 'zaplane_zenc_keep_ticket_20260924', $state, false );
	}
	if ( empty( $state['ticket_id'] ) ) {
		$state['ticket_id'] = $ticket::create( [
			'user_id' => $admin, 'type_id' => 23, 'product_id' => 6, 'priority_id' => 24,
			'title' => '[Keep] Zaplane / ZenCommunity Pro real ticket workflow',
			'content' => 'Human-style support ticket: automation should add a real support reply.',
			'meta' => [ 'is_ai_enabled' => false ],
		], $admin );
		update_option( 'zaplane_zenc_keep_ticket_20260924', $state, false );
	}
	report( 'TICKET_TEST_RECORD', $state );
	run_saved_workflow( (int) $state['workflow_id'] );
	global $wpdb;
	$replies = $wpdb->get_results( $wpdb->prepare(
		"SELECT id,ticket_id,user_id,content,is_note FROM {$wpdb->prefix}zenc_ticket_conversations WHERE ticket_id=%d ORDER BY id DESC LIMIT 5",
		(int) $state['ticket_id'] ), ARRAY_A );
	report( 'SAVED_TICKET_REPLIES', $replies );
	if ( ! array_filter( $replies, static fn($r) => str_contains( $r['content'], '[Zaplane integration test]' ) ) ) {
		throw new RuntimeException( 'Real reply not found in ZenCommunity Pro ticket database.' );
	}
	report( 'TICKET_WORKFLOW_RESULT', 'PASS_RETAINED' );
	exit;
}

if ( 'group_message' === $command ) {
	$state = (array) get_option( 'zaplane_zenc_keep_group_message_20260924', [] );
	$group_id = 19;
	$text = '[Zaplane integration test] Space post announced by automated group chat. Keep this message.';
	if ( empty( $state['workflow_id'] ) ) {
		$state['workflow_id'] = make_workflow(
			'[Keep] ZenCommunity space post → group chat announcement',
			'post_in_space', 'zencommunity/feed/created', [ 'group_id' => $group_id ],
			'send_group_message', [ 'group_id' => $group_id, 'sender_id' => $admin,
				'message' => $text ] );
		update_option( 'zaplane_zenc_keep_group_message_20260924', $state, false );
	}
	if ( empty( $state['feed_id'] ) ) {
		$state['feed_id'] = $feed::create( $group_id, [
			'title' => '[Keep] ZenCommunity group message automation announcement',
			'content' => 'An actual post should cause an automated group chat message.',
			'type' => 'post', 'status' => 'published',
		], $admin );
		update_option( 'zaplane_zenc_keep_group_message_20260924', $state, false );
	}
	report( 'GROUP_MESSAGE_TEST_RECORD', $state );
	run_saved_workflow( (int) $state['workflow_id'] );
	global $wpdb;
	$messages = $wpdb->get_results( $wpdb->prepare(
		"SELECT id,group_id,sender_id,message FROM {$wpdb->prefix}zenc_messages WHERE group_id=%d AND message LIKE %s ORDER BY id DESC LIMIT 4",
		$group_id, '%[Zaplane integration test]%' ), ARRAY_A );
	report( 'SAVED_GROUP_MESSAGES', $messages );
	if ( ! array_filter( $messages, static fn($m) => str_contains( $m['message'], 'Space post announced' ) ) ) {
		throw new RuntimeException( 'Group chat message not found in ZenCommunity Pro database.' );
	}
	report( 'GROUP_MESSAGE_WORKFLOW_RESULT', 'PASS_RETAINED' );
	exit;
}

if ( 'register' === $command ) {
	$state = (array) get_option( 'zaplane_zenc_keep_register_20260924', [] );
	$username = 'zaplane_zenc_keep_20260924';
	$group_id = 20; // Dedicated existing Auto Add Space, not a normal community.
	if ( empty( $state['workflow_id'] ) ) {
		$state['workflow_id'] = make_workflow(
			'[Keep / Paused after test] ZenCommunity registration → join test space',
			'user_registers', 'zencommunity/profile/created', [],
			'add_user_space', [ 'group_id' => $group_id,
				'user_id' => '{{trigger.user_id}}', 'role' => 'member' ] );
		update_option( 'zaplane_zenc_keep_register_20260924', $state, false );
	}
	if ( empty( $state['user_id'] ) ) {
		$existing_id = username_exists( $username );
		$user_id = $existing_id ?: wp_create_user( $username,
			wp_generate_password( 30 ), 'zaplane-zenc-keep-20260924@example.invalid' );
		if ( is_wp_error( $user_id ) ) { throw new RuntimeException( $user_id->get_error_message() ); }
		$state['user_id'] = (int) $user_id;
		update_option( 'zaplane_zenc_keep_register_20260924', $state, false );
	}
	$profile = \ZenCommunity\Database\Models\Profile::class;
	if ( ! $profile::exists( (int) $state['user_id'] ) ) {
		$profile::create( (int) $state['user_id'], [
			'username' => $username, 'first_name' => 'Zaplane Test',
			'last_name' => 'Member', 'status' => 'active',
		] );
	}
	report( 'REGISTRATION_TEST_RECORD', $state );
	run_saved_workflow( (int) $state['workflow_id'] );
	global $wpdb;
	$membership = $wpdb->get_row( $wpdb->prepare(
		"SELECT group_id,user_id,role,status FROM {$wpdb->prefix}zenc_group_members WHERE group_id=%d AND user_id=%d",
		$group_id, (int) $state['user_id'] ), ARRAY_A );
	report( 'SAVED_MEMBERSHIP', $membership );
	if ( ! $membership || 'active' !== $membership['status'] ) {
		throw new RuntimeException( 'Community registration did not add a real active space membership.' );
	}
	\Zaplane\Models\Workflow::find( (int) $state['workflow_id'] )->pause();
	do_action( 'zaplane_workflow_updated', (int) $state['workflow_id'] );
	report( 'REGISTRATION_WORKFLOW_RESULT', 'PASS_RETAINED_PAUSED' );
	exit;
}

if ( 'private' === $command ) {
	$state = (array) get_option( 'zaplane_zenc_keep_private_20260924', [] );
	$recipient = 39; // Dedicated Zaplane subscriber created in registration test.
	$group_id = 19;
	$message = '[Zaplane integration test] First private message triggered by a real test-space post. Keep.';
	$announcement = '[Zaplane integration test] First private conversation started. Keep this group chat record.';
	if ( empty( $state['private_started_workflow'] ) ) {
		$state['private_started_workflow'] = make_workflow(
			'[Keep / Paused after test] Private conversation started → chat announcement',
			'private_conversation', 'zencommunity_pro/private/message/created', [],
			'send_group_message', [ 'group_id' => $group_id, 'sender_id' => $admin,
				'message' => $announcement ] );
		update_option( 'zaplane_zenc_keep_private_20260924', $state, false );
	}
	if ( empty( $state['post_to_private_workflow'] ) ) {
		$state['post_to_private_workflow'] = make_workflow(
			'[Keep / Paused after test] Space post → private message to test member',
			'post_in_space', 'zencommunity/feed/created', [ 'group_id' => $group_id ],
			'send_private_message', [ 'sender_id' => $admin, 'receiver_id' => $recipient,
				'message' => $message ] );
		update_option( 'zaplane_zenc_keep_private_20260924', $state, false );
	}
	if ( empty( $state['feed_id'] ) ) {
		$state['feed_id'] = $feed::create( $group_id, [
			'title' => '[Keep] ZenCommunity first private conversation integration test',
			'content' => 'Posting in this dedicated space initiates a test-only private message.',
			'type' => 'post', 'status' => 'published',
		], $admin );
		update_option( 'zaplane_zenc_keep_private_20260924', $state, false );
	}
	report( 'PRIVATE_TEST_RECORD', $state );
	run_saved_workflow( (int) $state['post_to_private_workflow'] );
	run_saved_workflow( (int) $state['private_started_workflow'] );
	global $wpdb;
	$messages = $wpdb->get_results( $wpdb->prepare(
		"SELECT id,group_id,sender_id,receiver_id,message FROM {$wpdb->prefix}zenc_messages WHERE (sender_id=%d AND receiver_id=%d) OR (group_id=%d AND message LIKE %s) ORDER BY id DESC LIMIT 10",
		$admin, $recipient, $group_id, '%First private conversation started%' ), ARRAY_A );
	report( 'SAVED_PRIVATE_AND_ANNOUNCEMENT', $messages );
	$private = array_filter( $messages, static fn($m) => null === $m['group_id']
		&& (int) $m['receiver_id'] === $recipient && str_contains( $m['message'], 'First private message' ) );
	$group = array_filter( $messages, static fn($m) => (int) $m['group_id'] === $group_id
		&& str_contains( $m['message'], 'First private conversation started' ) );
	if ( ! $private || ! $group ) {
		throw new RuntimeException( 'Private conversation or downstream group message is missing.' );
	}
	foreach ( [ 'post_to_private_workflow', 'private_started_workflow' ] as $key ) {
		\Zaplane\Models\Workflow::find( (int) $state[$key] )->pause();
		do_action( 'zaplane_workflow_updated', (int) $state[$key] );
	}
	report( 'PRIVATE_WORKFLOWS_RESULT', 'PASS_RETAINED_PAUSED' );
	exit;
}

if ( 'pause_global' === $command ) {
	$state = (array) get_option( 'zaplane_zenc_keep_ticket_20260924', [] );
	$id = absint( $state['workflow_id'] ?? 0 );
	$wf = $id ? \Zaplane\Models\Workflow::find( $id ) : null;
	if ( $wf ) {
		$wf->pause();
		do_action( 'zaplane_workflow_updated', $id );
	}
	report( 'GLOBAL_TICKET_TEST_WORKFLOW_PAUSED', $id );
	exit;
}

if ( 'ticket_create_action' === $command ) {
	$state = (array) get_option( 'zaplane_zenc_keep_ticket_create_action_20260924', [] );
	$group_id = 19;
	if ( empty( $state['workflow_id'] ) ) {
		$state['workflow_id'] = make_workflow(
			'[Keep / Paused after test] Space post → create ZenCommunity support ticket',
			'post_in_space', 'zencommunity/feed/created', [ 'group_id' => $group_id ],
			'create_ticket', [
				'user_id' => $admin, 'type_id' => 23, 'product_id' => 6, 'priority_id' => 24,
				'title' => '[Keep] Zaplane workflow-created support ticket',
				'content' => 'Ticket created through the real Zaplane action from a ZenCommunity post.',
			] );
		update_option( 'zaplane_zenc_keep_ticket_create_action_20260924', $state, false );
	} else {
		\Zaplane\Models\Workflow::find( (int) $state['workflow_id'] )->activate();
		do_action( 'zaplane_workflow_updated', (int) $state['workflow_id'] );
	}
	add_filter( 'zencommunity/ticket/ai_enabled', static fn() => false, 10, 3 );
	try {
		if ( empty( $state['feed_id'] ) ) {
			$state['feed_id'] = $feed::create( $group_id, [
				'title' => '[Keep] Test-space post creating a ZenCommunity Pro ticket',
				'content' => 'A real Zaplane action will create a support ticket for this post.',
				'type' => 'post', 'status' => 'published',
			], $admin );
			update_option( 'zaplane_zenc_keep_ticket_create_action_20260924', $state, false );
		}
		$run = run_saved_workflow( (int) $state['workflow_id'] );
		foreach ( $run['nodes'] as $node ) {
			if ( 2 === (int) $node['node_key'] ) {
				$state['ticket_id'] = absint( $node['output_json']['data']['ticket_id'] ?? 0 );
			}
		}
		if ( empty( $state['ticket_id'] ) || ! $ticket::exists( (int) $state['ticket_id'] ) ) {
			throw new RuntimeException( 'Ticket action output did not produce a real ticket.' );
		}
		update_option( 'zaplane_zenc_keep_ticket_create_action_20260924', $state, false );
		report( 'CREATED_BY_ACTION', $state );
		$created_ticket = $ticket::by_id( (int) $state['ticket_id'] );
		report( 'SAVED_CREATED_TICKET', [ 'id' => $created_ticket['id'],
			'status' => $created_ticket['status'], 'title' => $created_ticket['title'],
			'meta' => $created_ticket['meta'] ] );
		report( 'CREATE_TICKET_ACTION_RESULT', 'PASS_RETAINED_PAUSED' );
	} finally {
		$wf = \Zaplane\Models\Workflow::find( (int) $state['workflow_id'] );
		if ( $wf ) { $wf->pause(); do_action( 'zaplane_workflow_updated', (int) $state['workflow_id'] ); }
	}
	exit;
}

if ( 'reaction_notification' === $command ) {
	$state = (array) get_option( 'zaplane_zenc_keep_reaction_notification_20260924', [] );
	$recipient = 39;
	$message = '[Zaplane integration test] A real post reaction generated this notification. Keep.';
	if ( empty( $state['workflow_id'] ) ) {
		$state['workflow_id'] = make_workflow(
			'[Keep / Paused after test] Post reaction → member notification',
			'post_reacted', 'zencommunity/feed/react', [],
			'send_notification', [ 'user_id' => $recipient,
				'message' => $message, 'notification_type' => 'custom' ] );
		update_option( 'zaplane_zenc_keep_reaction_notification_20260924', $state, false );
	} else {
		\Zaplane\Models\Workflow::find( (int) $state['workflow_id'] )->activate();
		do_action( 'zaplane_workflow_updated', (int) $state['workflow_id'] );
	}
	try {
		if ( empty( $state['reaction_fired'] ) ) {
			$feed::react( 32, 'love', $admin );
			$state['reaction_fired'] = true;
			update_option( 'zaplane_zenc_keep_reaction_notification_20260924', $state, false );
		}
		run_saved_workflow( (int) $state['workflow_id'] );
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT n.id,n.event_type,n.message,nu.to_user_id FROM {$wpdb->prefix}zenc_notifications n INNER JOIN {$wpdb->prefix}zenc_notified_users nu ON nu.notification_id=n.id WHERE nu.to_user_id=%d AND n.message=%s ORDER BY n.id DESC",
			$recipient, $message ), ARRAY_A );
		report( 'SAVED_REACTION_NOTIFICATIONS', $rows );
		if ( ! $rows ) { throw new RuntimeException( 'The reaction notification was not persisted.' ); }
		report( 'REACTION_NOTIFICATION_RESULT', 'PASS_RETAINED_PAUSED' );
	} finally {
		$wf = \Zaplane\Models\Workflow::find( (int) $state['workflow_id'] );
		if ( $wf ) { $wf->pause(); do_action( 'zaplane_workflow_updated', (int) $state['workflow_id'] ); }
	}
	exit;
}

if ( 'event_poll' === $command ) {
	$state = (array) get_option( 'zaplane_zenc_keep_event_poll_20260924', [] );
	$group_id = 19;
	$start_at = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
	$end_at = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS + 7200 );
	if ( empty( $state['event_notice_workflow'] ) ) {
		$state['event_notice_workflow'] = make_workflow(
			'[Keep / Paused after test] Event Created → test-space chat announcement',
			'event_created', 'zencommunity/event/created', [],
			'send_group_message', [ 'sender_id' => $admin, 'group_id' => $group_id,
				'message' => '[Zaplane integration test] A real ZenCommunity event was created. Keep.' ] );
		update_option( 'zaplane_zenc_keep_event_poll_20260924', $state, false );
	}
	if ( empty( $state['event_create_workflow'] ) ) {
		$state['event_create_workflow'] = make_workflow(
			'[Keep / Paused after test] Space post → create event',
			'post_in_space', 'zencommunity/feed/created', [ 'group_id' => $group_id ],
			'create_event', [ 'group_id' => $group_id,
				'title' => '[Keep] Zaplane real workflow community event',
				'start_at' => $start_at, 'end_at' => $end_at,
				'description' => 'A real workflow-created event, retained for testing.' ] );
		update_option( 'zaplane_zenc_keep_event_poll_20260924', $state, false );
	}
	if ( empty( $state['poll_create_workflow'] ) ) {
		$state['poll_create_workflow'] = make_workflow(
			'[Keep / Paused after test] Space post → create poll',
			'post_in_space', 'zencommunity/feed/created', [ 'group_id' => $group_id ],
			'create_poll', [ 'group_id' => $group_id, 'user_id' => $admin,
				'title' => '[Keep] Zaplane real workflow poll question',
				'options' => "Option A\nOption B",
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS ) ] );
		update_option( 'zaplane_zenc_keep_event_poll_20260924', $state, false );
	}
	try {
		if ( empty( $state['feed_id'] ) ) {
			$state['feed_id'] = $feed::create( $group_id, [
				'title' => '[Keep] Test-space post spawning a poll and event',
				'content' => 'The actual post triggers two ZenCommunity actions and one event-created trigger.',
				'type' => 'post', 'status' => 'published',
			], $admin );
			update_option( 'zaplane_zenc_keep_event_poll_20260924', $state, false );
		}
		$event_run = run_saved_workflow( (int) $state['event_create_workflow'] );
		$poll_run = run_saved_workflow( (int) $state['poll_create_workflow'] );
		$notice_run = run_saved_workflow( (int) $state['event_notice_workflow'] );
		foreach ( $event_run['nodes'] as $node ) {
			if ( 2 === (int) $node['node_key'] ) {
				$state['event_id'] = absint( $node['output_json']['data']['event_id'] ?? 0 );
			}
		}
		foreach ( $poll_run['nodes'] as $node ) {
			if ( 2 === (int) $node['node_key'] ) {
				$state['poll_id'] = absint( $node['output_json']['data']['feed_id'] ?? 0 );
			}
		}
		if ( empty( $state['event_id'] ) || empty( $state['poll_id'] ) ) {
			throw new RuntimeException( 'Event or poll action returned no persisted ID.' );
		}
		update_option( 'zaplane_zenc_keep_event_poll_20260924', $state, false );
		$event_record = \ZenCommunity\Database\Models\Event::by_id( $state['event_id'] );
		$poll_record = $feed::by_id( $state['poll_id'] );
		report( 'SAVED_EVENT_POLL', [
			'event_id' => $state['event_id'], 'event_title' => $event_record['title'] ?? null,
			'poll_id' => $state['poll_id'], 'poll_type' => $poll_record['type'] ?? null ] );
		if ( ( $poll_record['type'] ?? '' ) !== 'poll' ) {
			throw new RuntimeException( 'Created feed was not a ZenCommunity poll.' );
		}
		report( 'EVENT_POLL_RESULT', 'PASS_RETAINED_PAUSED' );
	} finally {
		foreach ( [ 'event_notice_workflow', 'event_create_workflow', 'poll_create_workflow' ] as $key ) {
			$wf = \Zaplane\Models\Workflow::find( (int) $state[$key] );
			if ( $wf ) { $wf->pause(); do_action( 'zaplane_workflow_updated', (int) $state[$key] ); }
		}
	}
	exit;
}

if ( 'verify_final' === $command ) {
	$poll = $feed::by_id( 35 );
	report( 'POLL_READBACK', [ 'id' => $poll['id'] ?? null, 'type' => $poll['type'] ?? null ] );
	if ( ( $poll['type'] ?? '' ) !== 'poll' ) { throw new RuntimeException( 'Poll readback failed.' ); }
	foreach ( [245,246,247,248,249,250,251,252,253,254,255] as $id ) {
		$wf = \Zaplane\Models\Workflow::find( $id );
		if ( $wf ) {
			$wf->pause();
			do_action( 'zaplane_workflow_updated', $id );
			report( 'RETAINED_TEST_WORKFLOW', [ 'id' => $id, 'status' => $wf->status ] );
		}
	}
	report( 'FINAL_VERIFICATION', 'POLL_OK_ALL_TEST_WORKFLOWS_PAUSED_NO_DATA_DELETED' );
	exit;
}
