<?php
/**
 * Messenger and WhatsApp run on workflows: on while their "Add Event to
 * Inbox" / "Send Inbox Reply" workflows are active, with the connection
 * linked there. Their recipes, and the status the settings screen shows.
 */

require __DIR__ . '/_helpers.php';

use Zaplane\Modules\Inbox\Settings as IS;
use Zaplane\Modules\Inbox\Services\{Connectors, Outbound};
use Zaplane\Modules\Inbox\Channels\{Messenger, Whatsapp};
use Zaplane\Modules\Inbox\Models\{Identity, Message};
use Zaplane\Models\{Workflow, WorkflowVersion};

zt_run( function () {
	IS::save( [ 'answers' => [ 'enabled' => false, 'menu' => [] ], 'ai' => [ 'enabled' => false ] ] );
	$calls = [];
	zt_mock_meta( $calls );

	// Nothing set up: the channel is off and a reply says where to fix it.
	zt_connectors( [] );
	Messenger::reset();
	zt_ok( 'no workflow → Messenger is off', ! Messenger::enabled() && ! Whatsapp::enabled() );
	$cm = zt_conversation( 'messenger', 'human', [ 'customer' => 'PS_WF', 'account' => 'PAGE_WF' ] );
	$m  = Outbound::send( $cm, 'Hello', [ 'sender_type' => 'agent', 'sender_id' => 1 ] );
	zt_ok( '…a reply fails and points to the setup', 'failed' === $m->delivery_status && false !== strpos( (string) $m->error, 'Channels' ) && 0 === count( $calls ) );

	// Set up: the reply is handed to the "Send Inbox Reply" workflow.
	zt_fake_channel_credentials();
	zt_ok( 'receiving workflow active → Messenger is on', Messenger::enabled() );
	$got = null;
	add_action( 'zaplane/inbox/reply_requested', function ( $p ) use ( &$got ) {
		$got = $p;
	} );
	$m = Outbound::send( \Zaplane\Modules\Inbox\Services\Conversations::find( (int) $cm->id ), 'Thanks for writing!', [ 'sender_type' => 'agent', 'sender_id' => 1 ] );
	zt_ok( 'reply queued for the workflow, nothing sent yet', 'queued' === $m->delivery_status && 'messenger' === $got['source'] && (int) $m->id === $got['message_id'] && 0 === count( $calls ) );

	// The workflow's step sends it with its own connection.
	$node = fn( string $event, array $config = [] ) => [ '_connection_credentials' => [ 'page_access_token' => 'STEP_TOKEN' ], 'data' => [ 'event' => $event, 'config' => $config ] ];
	$out  = \Zaplane\Integrations\Messenger::execute_node( $node( 'inbox_send', [ 'message_id' => (string) $m->id ] ), [ 'keep' => 1 ] );
	$m    = Message::where( 'id', $m->id )->fresh()->first();
	zt_ok( '"Send Inbox Reply" delivers it and records the result', ! empty( $out['data']['success'] ) && 'sent' === $m->delivery_status && '' !== (string) $m->external_id && 1 === $out['data']['keep'] );
	zt_ok( '…with the step\'s connection', 1 === count( $calls ) && 'Thanks for writing!' === $calls[0]['body']['message']['text'] );
	$out = \Zaplane\Integrations\Messenger::execute_node( $node( 'inbox_send', [ 'message_id' => '999999999' ] ), [] );
	zt_ok( 'unknown message → a failed step, no crash', empty( $out['data']['success'] ) );

	// "Webhook Received" carries the whole delivery, signature included.
	update_option( 'zaplane_webhook_config', [ 'messenger' => [ 'app_secret' => 'zz_secret' ] ], false );
	$body = wp_json_encode( [ 'object' => 'page', 'entry' => [ [ 'id' => 'PAGE_WF', 'messaging' => [
		[ 'sender' => [ 'id' => 'PS_NEW' ], 'recipient' => [ 'id' => 'PAGE_WF' ], 'timestamp' => time() * 1000, 'message' => [ 'mid' => 'm_zz_wf_' . wp_rand(), 'text' => 'Is the red one in stock?' ] ],
		[ 'sender' => [ 'id' => 'PS_NEW' ], 'recipient' => [ 'id' => 'PAGE_WF' ], 'timestamp' => time() * 1000, 'postback' => [ 'title' => 'Delivery times', 'payload' => 'ZAPLANE_STARTER_0', 'mid' => 'm_zz_pb_' . wp_rand() ] ],
	] ] ] ] );
	$sig     = 'sha256=' . hash_hmac( 'sha256', $body, 'zz_secret' );
	$request = new WP_REST_Request( 'POST', '/' );
	$request->set_body( $body );
	$request->set_header( 'x_hub_signature_256', $sig );
	$parsed  = \Zaplane\Integrations\Messenger::parse_webhook_event( $request );
	$whole   = array_values( array_filter( $parsed['events'], fn( $e ) => 'webhook_received' === $e['event'] ) )[0] ?? null;
	zt_ok( 'the delivery fires "Webhook Received" with body + signature (postbacks too)', $whole && $body === $whole['payload']['body'] && $sig === $whole['payload']['signature'] && 2 === $whole['payload']['events'] );
	zt_ok( '"Message Received" still fires per text message', 1 === count( array_filter( $parsed['events'], fn( $e ) => 'message_received' === $e['event'] ) ) );

	add_filter( 'pre_http_request', fn( $pre, $a, $url ) => false !== strpos( $url, 'fields=' ) ? [ 'headers' => [], 'body' => '{"first_name":"Nila","last_name":"R"}', 'response' => [ 'code' => 200 ], 'cookies' => [] ] : $pre, 5, 3 );
	$out = \Zaplane\Integrations\Messenger::execute_node( $node( 'inbox_receive' ), $whole['payload'] );
	$in  = Message::where( 'channel', 'messenger' )->where( 'body', 'Is the red one in stock?' )->fresh()->first();
	zt_ok( '"Add Event to Inbox" stores the message and the tapped question', ! empty( $out['data']['success'] ) && 2 === $out['data']['stored'] && $in && (bool) Message::where( 'channel', 'messenger' )->where( 'body', 'Delivery times' )->fresh()->first() );
	$forged = \Zaplane\Integrations\Messenger::execute_node( $node( 'inbox_receive' ), [ 'body' => $body, 'signature' => 'sha256=nope' ] );
	zt_ok( 'a delivery not signed by Meta is refused', empty( $forged['data']['success'] ) && false !== strpos( $forged['data']['error'], 'App Secret' ) );

	// WhatsApp has the same two steps.
	zt_ok( 'WhatsApp offers Add Event to Inbox and Send Inbox Reply', isset( \Zaplane\Integrations\Whatsapp::get_actions()['inbox_receive'], \Zaplane\Integrations\Whatsapp::get_actions()['inbox_send'] ) && isset( \Zaplane\Integrations\Whatsapp::get_triggers()['webhook_received'] ) );

	// Real workflows decide it: an active one with the step turns the channel on.
	remove_all_filters( 'zaplane/inbox/connector_nodes' );
	Connectors::reset();
	$before = Connectors::receives( 'whatsapp' );
	$wf     = Workflow::create( [ 'user_id' => 1, 'title' => 'ZZ WhatsApp in', 'status' => 'active' ] );
	WorkflowVersion::create( [ 'workflow_id' => $wf->id, 'is_active' => 1, 'version_number' => 1, 'graph_hash' => 'zz', 'graph_json' => [ 'nodes' => [
		[ 'id' => '1', 'data' => [ 'app' => 'whatsapp', 'event' => 'webhook_received' ] ],
		[ 'id' => '2', 'data' => [ 'app' => 'whatsapp', 'event' => 'inbox_receive', 'connection_id' => 77 ] ],
	], 'edges' => [] ] ] );
	Connectors::reset();
	zt_ok( 'an active workflow with "Add Event to Inbox" turns WhatsApp on, with its connection', ! $before && Connectors::receives( 'whatsapp' ) && 77 === Connectors::connection_id( 'whatsapp' ) );
	$wf->status = 'paused';
	$wf->save();
	Connectors::reset();
	zt_ok( 'pausing it turns WhatsApp off', ! Connectors::receives( 'whatsapp' ) );

	// Recipes and the settings screen.
	$m_recipe = require ZAPLANE_ROOT_DIR_PATH . 'includes/recipes/shipped/messenger-inbox.php';
	$bp       = json_decode( \Zaplane\Recipes\RecipeCompiler::record( $m_recipe )['blueprint'], true );
	zt_ok( 'Messenger recipe: two workflows, tagged inbox, for the messenger channel', 2 === count( $bp['workflows'] ) && [ 'inbox' ] === $bp['tags'] && [ 'channel' => 'messenger' ] === $bp['inbox'] );
	$catalog = array_column( Connectors::catalog(), null, 'slug' );
	zt_ok( 'settings list Messenger, WhatsApp and Comments as Inbox recipes', isset( $catalog['messenger-inbox'], $catalog['whatsapp-inbox'], $catalog['wordpress-comments-inbox'] ) && 'channel' === $catalog['whatsapp-inbox']['kind'] && 'source' === $catalog['wordpress-comments-inbox']['kind'] );

	// The setup wizard can't create workflows without their connections.
	global $wpdb;
	$before  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}zaplane_workflows" );
	$recipe  = \Zaplane\Models\Recipe::where( 'slug', 'whatsapp-inbox' )->fresh()->first();
	$setup   = new WP_REST_Request( 'POST', "/zaplane/v1/recipes/{$recipe->id}/setup" );
	$setup->set_header( 'content-type', 'application/json' );
	$setup->set_body( wp_json_encode( [ 'workflows' => [ 'receive' => true, 'deliver' => true ] ] ) );
	$res = rest_do_request( $setup );
	zt_ok( 'recipe setup without its connection is refused, nothing created', 400 === $res->get_status() && false !== strpos( $res->get_data()['message'], 'WhatsApp' ) && $before === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}zaplane_workflows" ) );
	$comments = \Zaplane\Models\Recipe::where( 'slug', 'wordpress-comments-inbox' )->fresh()->first();
	zt_ok( 'a recipe whose apps need no account still sets up', [] === array_values( array_filter( ( new \Zaplane\Services\RecipeGroupService() )->setup( $comments )['apps'], fn( $a ) => $a['requires_connection'] ) ) );

	// Turning a workflow on from the list runs the same checks as the editor.
	$bad = Workflow::create( [ 'user_id' => 1, 'title' => 'ZZ broken', 'status' => 'draft' ] );
	WorkflowVersion::create( [ 'workflow_id' => $bad->id, 'is_active' => 1, 'version_number' => 1, 'graph_hash' => 'zz2', 'graph_json' => [ 'nodes' => [
		[ 'id' => '1', 'type' => 'action', 'data' => [ 'app' => 'messenger', 'event' => 'inbox_send', 'config' => [] ] ],
	], 'edges' => [] ] ] );
	$res = ( new \Zaplane\Ajax\Workflows() )->updateStatus( [ 'id' => $bad->id, 'status' => 'active' ] );
	zt_ok( 'the Workflows list can\'t turn on a workflow that can\'t run', is_wp_error( $res ) && 'draft' === Workflow::find( $bad->id )->status );
} );
