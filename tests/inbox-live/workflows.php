<?php
/**
 * Workflows and the Inbox: one answerer per conversation, recording what
 * workflows send, the trigger gate, and Memory reading the Inbox.
 */

require __DIR__ . '/_helpers.php';

use Zaplane\Modules\Inbox\Settings as IS;
use Zaplane\Modules\Inbox\Services\{WorkflowSends, AiResponder, Presenter};
use Zaplane\Modules\Inbox\Models\{Conversation, Message};
use Zaplane\Models\{Workflow, WorkflowVersion, Run};
use Zaplane\Integrations\{Messenger, Whatsapp, Memory};

zt_run( function () {
	$calls = [];
	zt_mock_meta( $calls );
	IS::save( [ 'channels' => [ 'messenger' => [ 'enabled' => true, 'connection_id' => 1 ], 'whatsapp' => [ 'enabled' => true, 'connection_id' => 1 ] ] ] );

	$graph = fn( $mode ) => [ 'nodes' => [
		[ 'id' => '1', 'data' => [ 'app' => 'messenger', 'event' => 'message_received' ] ],
		[ 'id' => '2', 'data' => [ 'app' => 'ai-agent', 'event' => 'run_agent' ] ],
		[ 'id' => '3', 'data' => [ 'app' => 'messenger', 'event' => 'send_text', 'config' => $mode ? [ 'inbox_mode' => $mode ] : [] ] ],
	], 'edges' => [] ];
	$wf  = Workflow::create( [ 'user_id' => 1, 'title' => 'ZZ AI Messenger bot', 'status' => 'active' ] );
	$ver = WorkflowVersion::create( [ 'workflow_id' => $wf->id, 'is_active' => 1, 'version_number' => 1, 'graph_hash' => 'a', 'graph_json' => $graph( '' ) ] );
	$run = Run::create( [ 'workflow_id' => $wf->id, 'workflow_version_id' => $ver->id, 'status' => 'running', 'is_test' => 0, 'trigger_data' => [] ] );
	zt_ok( 'settings list workflows that also reply', in_array( 'ZZ AI Messenger bot', array_column( WorkflowSends::senders( 'messenger' ), 'name' ), true ) );

	$cv   = zt_conversation( 'messenger', 'bot', [ 'customer' => 'PSID_W', 'account' => 'PAGE1' ] );
	$node = fn( $cfg, $with_run = true ) => array_filter( [ '_run_id' => $with_run ? $run->id : null, '_connection_credentials' => [ 'page_access_token' => 'T' ], 'data' => [ 'event' => 'send_text', 'name' => 'Send', 'config' => $cfg ] ], fn( $v ) => null !== $v );

	$out  = Messenger::execute_node( $node( [ 'recipient_id' => 'PSID_W', 'text' => 'Workflow answer' ] ), [] );
	$note = Message::where( 'conversation_id', $cv->id )->where( 'is_note', 1 )->fresh()->first();
	zt_ok( 'reply held while the assistant answers (nothing sent)', ! empty( $out['data']['skipped'] ) && 0 === count( $calls ) );
	zt_ok( 'held reply kept as a note, labelled with the workflow', $note && ! empty( $note->meta['held'] ) && 'Workflow · ZZ AI Messenger bot' === Presenter::message( $note )['sender_name'] );

	Messenger::execute_node( $node( [ 'recipient_id' => 'PSID_W', 'text' => 'Order shipped', 'inbox_mode' => 'always' ] ), [] );
	$c = Conversation::where( 'id', $cv->id )->fresh()->first();
	zt_ok( '"always send" goes out, is recorded, and leaves the assistant on', 1 === count( $calls ) && 'bot' === $c->handler && 'workflow' === zt_last( $cv )->sender_type );
	zt_ok( 'Messenger send uses the Authorization header, not the URL', false === strpos( $calls[0]['url'], 'access_token' ) && 'zaplane_inbox' === $calls[0]['body']['message']['metadata'] );

	$c->handler = 'workflow'; $c->ai_enabled = false; $c->save();
	Messenger::execute_node( $node( [ 'recipient_id' => 'PSID_W', 'text' => 'Hi from the workflow' ] ), [] );
	zt_ok( 'sent when workflows own the conversation', 2 === count( $calls ) );
	Messenger::execute_node( $node( [ 'recipient_id' => 'PSID_OTHER', 'text' => 'stranger' ] ), [] );
	zt_ok( 'unknown customer: sent, not recorded', 3 === count( $calls ) && 0 === Message::where( 'body', 'stranger' )->fresh()->count() );

	// WhatsApp: number normalised, templates never held.
	$cw = zt_conversation( 'whatsapp', 'bot', [ 'customer' => '8801711000000', 'account' => 'PNID1' ] );
	$wn = fn( $event, $cfg ) => [ '_run_id' => $run->id, '_connection_credentials' => [ 'access_token' => 'T', 'phone_number_id' => 'PNID1' ], 'data' => [ 'event' => $event, 'name' => 'WA', 'config' => $cfg ] ];
	$out = Whatsapp::execute_node( $wn( 'send_text', [ 'to' => '+880 1711-000000', 'body' => 'wa reply' ] ), [] );
	zt_ok( 'WhatsApp reply held (+ and spaces normalised)', ! empty( $out['data']['skipped'] ) );
	Whatsapp::execute_node( $wn( 'send_template', [ 'to' => '8801711000000', 'template_name' => 'order_update' ] ), [] );
	zt_ok( 'WhatsApp template sent and recorded', 'Template: order_update' === zt_last( $cw )->body );

	// Trigger gate: reply workflows don't even start for someone else's conversation.
	$trig = fn( $v ) => [ 'workflow_id' => $wf->id, 'workflow_version_id' => $v->id, 'graph_node' => [ 'data' => [ 'app' => 'messenger', 'event' => 'message_received' ] ] ];
	$alert = WorkflowVersion::create( [ 'workflow_id' => $wf->id, 'is_active' => 0, 'version_number' => 2, 'graph_hash' => 'b', 'graph_json' => [ 'nodes' => [ [ 'id' => '1', 'data' => [ 'app' => 'messenger', 'event' => 'message_received' ] ], [ 'id' => '2', 'data' => [ 'app' => 'slack', 'event' => 'send_message' ] ] ] ] ] );
	$c->handler = 'bot'; $c->ai_enabled = true; $c->save();
	$p = [ 'sender_id' => 'PSID_W', 'recipient_id' => 'PAGE1' ];
	zt_ok( 'reply workflow skipped while the assistant answers', false === WorkflowSends::should_start( true, $trig( $ver ), $p ) );
	zt_ok( 'alert workflow still runs', true === WorkflowSends::should_start( true, $trig( $alert ), $p ) );
	$c->handler = 'workflow'; $c->save();
	zt_ok( 'reply workflow runs when workflows own it', true === WorkflowSends::should_start( true, $trig( $ver ), $p ) );

	// The assistant isn't silenced by a workflow notification.
	$cb = zt_conversation( 'web', 'bot' );
	$q  = zt_message( $cb, 'Where is my order?' );
	zt_message( $cb, 'Order shipped', 'workflow' );
	zt_ok( 'assistant still answers after a workflow notification', AiResponder::should_answer( Conversation::where( 'id', $cb->id )->fresh()->first(), (int) $q->id ) );

	// Memory reading the Inbox conversation.
	$cm = zt_conversation( 'messenger', 'workflow', [ 'customer' => 'PSID_M' ] );
	zt_message( $cm, 'Do you have size L?' );
	zt_message( $cm, 'Yes, size L is in stock.', 'workflow' );
	zt_message( $cm, 'I also added free delivery.', 'agent', [ 'sender_id' => 1 ] );
	zt_message( $cm, 'secret team note', 'agent', [ 'sender_id' => 1, 'is_note' => 1 ] );
	zt_message( $cm, '', 'agent', [ 'sender_id' => 1, 'meta' => [ 'deleted_at' => '2026-01-01' ] ] );
	zt_message( $cm, 'Great, how long is delivery?' );
	$get = fn( $cfg ) => Memory::execute_node( [ 'data' => [ 'event' => 'get_history', 'config' => $cfg ] ], [] )['data'];
	$h   = $get( [ 'conversation_key' => 'messenger:PSID_M', 'source' => 'inbox', 'limit' => 10 ] )['history'];
	zt_ok( 'Memory from the Inbox: team reply in, notes/deleted out, pending question left for the task', 3 === count( $h ) && 'I also added free delivery.' === $h[2]['content'] && 'user' === $h[0]['role'] );
	zt_ok( 'Memory limit counts real turns', 1 === count( $get( [ 'conversation_key' => 'messenger:PSID_M', 'source' => 'inbox', 'limit' => 1 ] )['history'] ) );
	$r = Memory::execute_node( [ 'data' => [ 'event' => 'append', 'config' => [ 'conversation_key' => 'messenger:PSID_M', 'source' => 'inbox', 'role' => 'user', 'content' => 'x' ] ] ], [] )['data'];
	zt_ok( 'no separate saving while the Inbox holds the conversation', ! empty( $r['skipped'] ) );
	zt_ok( 'unknown customer falls back to saved turns', empty( $get( [ 'conversation_key' => 'messenger:NOBODY', 'source' => 'inbox' ] )['source'] ) );
} );
