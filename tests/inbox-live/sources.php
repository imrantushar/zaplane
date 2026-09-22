<?php
/**
 * Workflow sources: anything a workflow reads can reach the inbox, and a
 * reply goes back out through a workflow. The WordPress Comments recipe is
 * the first user.
 */

require __DIR__ . '/_helpers.php';

use Zaplane\Integrations\{Inbox, Wordpress};
use Zaplane\Modules\Inbox\Settings as IS;
use Zaplane\Modules\Inbox\Services\{Conversations, KnowledgeAnswer, Outbound, Presenter, Sources};
use Zaplane\Modules\Inbox\Models\{Contact, Message};

zt_run( function () {
	IS::save( [ 'answers' => [ 'enabled' => true, 'menu' => [] ], 'ai' => [ 'enabled' => false ] ] );
	delete_option( Sources::OPTION );

	$run = function ( string $class, string $event, array $config, array $input = [] ) {
		return $class::execute_node( [ 'data' => [ 'event' => $event, 'config' => $config ] ], $input );
	};

	// A real post and comment thread. (Output from the site's own comment
	// hooks is dropped so it can't garble the PASS lines.)
	ob_start();
	$post   = wp_insert_post( [ 'post_title' => 'ZZ Shipping FAQ', 'post_status' => 'publish', 'post_type' => 'post' ] );
	$root   = wp_insert_comment( [ 'comment_post_ID' => $post, 'comment_author' => 'Jane', 'comment_author_email' => 'jane.zz@example.com', 'comment_content' => 'Do you ship to Canada?', 'comment_approved' => 0 ] );
	$child  = wp_insert_comment( [ 'comment_post_ID' => $post, 'comment_parent' => $root, 'comment_author' => 'Jane', 'comment_author_email' => 'jane.zz@example.com', 'comment_content' => 'And how long does it take?', 'comment_approved' => 1 ] );
	ob_end_clean();
	$ref    = new ReflectionMethod( Wordpress::class, 'resolve_trigger' );
	$trig   = $ref->invoke( null, [ 'event' => 'comment_post', 'config' => [] ], [ $child, 1 ] );
	zt_ok( 'comment trigger: the comment that was posted (not the site\'s first one)', (int) $trig['comment_ID'] === $child && 'And how long does it take?' === $trig['comment_content'] );
	zt_ok( 'comment trigger: post title/URL, thread root, reply flag, team flag', 'ZZ Shipping FAQ' === $trig['post_title'] && get_permalink( $post ) === $trig['post_url'] && $root === $trig['thread_id'] && true === $trig['is_reply'] && false === $trig['author_is_team'] );

	// Add Incoming Message.
	$in  = [ 'keep' => 'me' ];
	$add = fn( array $extra = [] ) => $run( Inbox::class, 'receive_message', array_merge( [
		'source'       => 'WP Comments',
		'source_label' => 'Comments',
		'thread_id'    => 'comment-' . $root,
		'message_id'   => (string) $root,
		'text'         => '<b>Do you ship to Canada?</b>',
		'name'         => 'Jane',
		'email'        => 'jane.zz@example.com',
		'link_url'     => get_permalink( $post ),
		'link_title'   => 'ZZ Shipping FAQ',
	], $extra ), $in );
	$r    = $add()['data'];
	$conv = Conversations::find( (int) $r['conversation_id'] );
	zt_ok( 'a source message makes a conversation filed under the source', $conv && 'wp_comments' === $conv->channel && 'Comments' === Sources::label( 'wp_comments' ) && 'me' === $r['keep'] );
	zt_ok( '…answered by the team by default (no public auto answers)', 'human' === $conv->handler && ! KnowledgeAnswer::applies( $conv ) );
	zt_ok( '…text only, contact from name + email', 'Do you ship to Canada?' === Message::where( 'id', $r['message_id'] )->fresh()->first()->body && 'jane.zz@example.com' === Contact::where( 'id', (int) $conv->contact_id )->fresh()->first()->email );
	$shown = Presenter::conversation( Conversations::find( (int) $conv->id ) );
	zt_ok( 'the inbox shows the source name and the page', 'Comments' === $shown['channel_label'] && get_permalink( $post ) === $shown['link']['url'] && 'ZZ Shipping FAQ' === $shown['link']['title'] );
	zt_ok( 'the same message ID twice is added once', ! empty( $add()['data']['duplicate'] ) );
	$r2 = $add( [ 'message_id' => (string) $child, 'text' => 'And how long does it take?' ] )['data'];
	zt_ok( 'the same thread → the same conversation', (int) $r2['conversation_id'] === (int) $conv->id );
	zt_ok( 'built-in channel names are refused as a source', false === $run( Inbox::class, 'receive_message', [ 'source' => 'web', 'thread_id' => 'x', 'text' => 'y' ] )['data']['success'] );

	// Replying: no delivering workflow → refused with a reason.
	remove_all_actions( 'zaplane/inbox/reply_requested' );
	zt_connectors( [] );
	$m = Outbound::send( Conversations::find( (int) $conv->id ), 'Yes! 5–7 days.', [ 'sender_type' => 'agent', 'sender_id' => 1 ] );
	zt_ok( 'no workflow delivers replies → the reply fails and says why', 'failed' === $m->delivery_status && false !== strpos( (string) $m->error, 'Reply to Deliver' ) );

	// With one: it gets everything it needs to post the reply.
	$got = null;
	zt_connectors( [ [ 'app' => 'inbox', 'event' => 'reply_requested', 'config' => [ 'source' => 'wp_comments' ] ] ] );
	add_action( 'zaplane/inbox/reply_requested', function ( $p ) use ( &$got ) {
		$got = $p;
	} );
	$m = Outbound::send( Conversations::find( (int) $conv->id ), 'Yes! 5–7 days.', [ 'sender_type' => 'agent', 'sender_id' => 1 ] );
	zt_ok( 'reply → Reply to Deliver with text, source, thread and the comment to reply under', 'sent' === $m->delivery_status && 'Yes! 5–7 days.' === $got['text'] && 'wp_comments' === $got['source'] && 'comment-' . $root === $got['thread_id'] && (string) $child === $got['reply_to'] );
	zt_ok( '…and who sent it', 1 === $got['sender_user_id'] && '' !== $got['sender_name'] && is_email( $got['sender_email'] ) );
	$resolve = fn( array $cfg ) => Inbox::resolve_trigger( [ 'event' => 'reply_requested', 'config' => $cfg ], [ $got ] );
	zt_ok( 'the trigger runs only for its source', is_array( $resolve( [ 'source' => 'wp_comments' ] ) ) && false === $resolve( [ 'source' => 'tickets' ] ) && false === $resolve( [] ) );
	zt_ok( 'replies through a workflow can\'t be edited from the inbox', ! \Zaplane\Modules\Inbox\Services\MessageActions::can_change( $m )['ok'] );

	// The workflow posts it as a comment reply, as that user, approving the parent.
	$out = $run( Wordpress::class, 'reply_comment', [ 'parent_id' => $got['reply_to'], 'author_name' => 'x', 'author_email' => 'x@example.com', 'content' => $got['text'], 'user_id' => (string) $got['sender_user_id'] ] );
	$c   = get_comment( (int) ( $out['data']['comment_id'] ?? 0 ) );
	zt_ok( 'Reply to Comment as the user: their name, under the right comment', $c && 1 === (int) $c->user_id && (int) $c->comment_parent === $child && get_userdata( 1 )->display_name === $c->comment_author );
	wp_update_comment( [ 'comment_ID' => $child, 'comment_approved' => 0 ] );
	$run( Wordpress::class, 'reply_comment', [ 'parent_id' => $child, 'author_name' => 'x', 'author_email' => 'x@example.com', 'content' => 'ok' ] );
	zt_ok( '…and replying approves a comment waiting for moderation', '1' === (string) get_comment( $child )->comment_approved );

	$run( Inbox::class, 'confirm_delivery', [ 'message_id' => $m->id, 'external_id' => (string) $c->comment_ID ] );
	$m = Message::where( 'id', $m->id )->fresh()->first();
	zt_ok( 'Confirm Reply Delivered records where it was posted', 'sent' === $m->delivery_status && 'wp_comments:' . $c->comment_ID === $m->external_id );
	zt_ok( '…so the same comment coming back through the source is not added again', ! empty( $add( [ 'message_id' => (string) $c->comment_ID, 'text' => 'Yes! 5–7 days.', 'from_team' => '1' ] )['data']['duplicate'] ) );
	$run( Inbox::class, 'confirm_delivery', [ 'message_id' => $m->id, 'error' => 'Comments are closed.' ] );
	zt_ok( 'an error marks it failed with the reason', 'failed' === Message::where( 'id', $m->id )->fresh()->first()->delivery_status );

	// Written by the team (in wp-admin): recorded as a team reply.
	$r3 = $add( [ 'message_id' => '999001', 'text' => 'Answered in wp-admin', 'from_team' => '1' ] )['data'];
	$tm = Message::where( 'id', (int) $r3['message_id'] )->fresh()->first();
	zt_ok( '"Written by your team" → an outgoing team message, not a customer one', $tm && 'out' === $tm->direction && 'agent' === $tm->sender_type );

	// Answerer per source is a setting; the assistant only when chosen.
	Sources::save_answerers( [ 'wp_comments' => 'assistant' ] );
	$fresh = $add( [ 'thread_id' => 'comment-new-zz', 'message_id' => '999002', 'text' => 'Is there a warranty?' ] )['data'];
	zt_ok( 'choosing the assistant for a source lets automatic answers in (new threads)', KnowledgeAnswer::applies( Conversations::find( (int) $fresh['conversation_id'] ) ) );

	// The recipe.
	$recipe = require ZAPLANE_ROOT_DIR_PATH . 'includes/recipes/shipped/wordpress-comments-inbox.php';
	$record = \Zaplane\Recipes\RecipeCompiler::record( $recipe );
	$bp     = json_decode( $record['blueprint'], true );
	zt_ok( 'the Comments recipe compiles: 2 workflows, tagged inbox', 'group' === $record['type'] && 2 === count( $bp['workflows'] ) && [ 'inbox' ] === $bp['tags'] );
	\Zaplane\Recipes\Registry::instance()->sync( true );
	$req  = new WP_REST_Request( 'GET', '/zaplane/v1/recipes' );
	$req->set_param( 'tag', 'inbox' );
	$data = rest_do_request( $req )->get_data()['data'];
	zt_ok( 'recipes?tag=inbox lists it (and only tagged ones)', in_array( 'wordpress-comments-inbox', array_column( $data, 'slug' ), true ) && ! array_filter( $data, fn( $d ) => ! in_array( 'inbox', $d['tags'], true ) ) );
} );
