<?php
/**
 * Reply / edit / delete on messages, and the chat widget's rate limits.
 */

require __DIR__ . '/_helpers.php';

use Zaplane\Modules\Inbox\Services\MessageActions;
use Zaplane\Modules\Inbox\Models\Message;

zt_run( function () {
	$calls = [];
	zt_mock_meta( $calls );
	$req = function ( $method, $path, $params = [] ) {
		$r = new WP_REST_Request( $method, '/zaplane/v1/inbox/' . $path );
		foreach ( $params as $k => $v ) {
			$r->set_param( $k, $v );
		}
		return rest_do_request( $r );
	};

	$web = zt_conversation( 'web', 'human' );
	$in  = zt_message( $web, 'Is the blue kurta available in L?' );

	$d = $req( 'POST', "conversations/{$web->id}/messages", [ 'body' => 'Yes it is', 'reply_to' => $in->id ] )->get_data();
	zt_ok( 'reply keeps a quote of the original', $in->id === $d['message']['reply_to']['id'] && true === $d['message']['can_change'] );
	$mid = $d['message']['id'];
	$rev = $d['conversation']['revision'];

	$d = $req( 'POST', "conversations/{$web->id}/messages/{$mid}", [ 'body' => 'Yes, size L is in stock' ] )->get_data();
	zt_ok( 'edit: new text, marked edited, revision bumped', $d['message']['edited'] && $d['conversation']['revision'] === $rev + 1 );
	zt_ok( 'empty edit refused', 400 === $req( 'POST', "conversations/{$web->id}/messages/{$mid}", [ 'body' => ' ' ] )->get_status() );
	$d = $req( 'DELETE', "conversations/{$web->id}/messages/{$mid}" )->get_data();
	zt_ok( 'delete: text gone, marked deleted', $d['message']['deleted'] && '' === $d['message']['body'] );
	zt_ok( "customer's message can't be edited", 403 === $req( 'POST', "conversations/{$web->id}/messages/{$in->id}", [ 'body' => 'x' ] )->get_status() );

	$note = $req( 'POST', "conversations/{$web->id}/messages", [ 'body' => 'secret', 'is_note' => true ] )->get_data()['message'];
	zt_ok( "a reply to the customer can't quote a private note", 400 === $req( 'POST', "conversations/{$web->id}/messages", [ 'body' => 'x', 'reply_to' => $note['id'] ] )->get_status() );

	$m  = zt_conversation( 'messenger', 'human', [ 'customer' => 'PS_E' ] );
	$mm = zt_message( $m, 'hello', 'agent', [ 'sender_id' => 1 ] );
	$ch = MessageActions::can_change( $mm );
	zt_ok( "Messenger replies can't be edited (and say why)", ! $ch['ok'] && false !== strpos( $ch['reason'], 'Messenger' ) );

	// Widget rate limits: per visitor, on a real one-minute window.
	wp_set_current_user( 0 );
	$_SERVER['REMOTE_ADDR'] = '10.9.9.9';
	$token = fn() => rest_do_request( new WP_REST_Request( 'POST', '/zaplane/v1/inbox/widget/session' ) )->get_data()['token'];
	$send  = function ( $tok, $i ) {
		$r = new WP_REST_Request( 'POST', '/zaplane/v1/inbox/widget/messages' );
		$r->set_header( 'X-Zaplane-Visitor', $tok );
		$r->set_param( 'body', "rate test $i" );
		return rest_do_request( $r );
	};
	$a     = $token();
	$b     = $token();
	$codes = [];
	for ( $i = 1; $i <= 21; $i++ ) {
		$codes[] = $send( $a, $i )->get_status();
	}
	zt_ok( 'visitor: 20 a minute, then blocked', 20 === count( array_filter( $codes, fn( $c ) => 200 === $c ) ) && 429 === end( $codes ) );
	zt_ok( 'the block says how long to wait', false !== strpos( $send( $a, 22 )->get_data()['message'], 'second' ) );
	zt_ok( 'another visitor on the same IP is unaffected', 200 === $send( $b, 1 )->get_status() );
} );
