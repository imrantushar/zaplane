<?php
/**
 * Messenger / WhatsApp: common questions (Ice Breakers, conversation
 * starters), tappable buttons, and a tapped question getting its answer.
 */

require __DIR__ . '/_helpers.php';

use Zaplane\Modules\Inbox\Settings as IS;
use Zaplane\Modules\Inbox\Services\{CommonQuestions, KnowledgeAnswer};
use Zaplane\Modules\Inbox\Channels\{Messenger, Whatsapp};
use Zaplane\Modules\Inbox\Models\{Identity, Message};

zt_run( function () {
	$calls = [];
	zt_mock_meta( $calls );
	delete_option( CommonQuestions::STATUS_OPTION );

	IS::save( [ 'answers' => [ 'common_questions' => [ 'What is your return policy?', '  ', str_repeat( 'x', 120 ), 'Two', 'Three', 'Five?' ] ] ] );
	$saved = IS::get()['answers']['common_questions'];
	zt_ok( 'common questions cleaned: max 4, no blanks, 80 chars', 4 === count( $saved ) && 80 === mb_strlen( $saved[1] ) );

	zt_fake_channel_credentials();
	$r = Messenger::set_starters( [ 'What is your return policy?', 'What is the delivery charge?' ] );
	$c = end( $calls );
	zt_ok( 'Messenger Ice Breakers sent', $r['ok'] && false !== strpos( $c['url'], 'me/messenger_profile' ) && 'What is your return policy?' === $c['body']['ice_breakers'][0]['call_to_actions'][0]['question'] );
	zt_fake_channel_credentials();
	Messenger::set_starters( [] );
	$c = end( $calls );
	zt_ok( 'empty list removes them (DELETE)', 'DELETE' === $c['method'] && [ 'ice_breakers' ] === $c['body']['fields'] );
	zt_fake_channel_credentials();
	$r = Whatsapp::set_starters( [ 'What is the delivery charge?' ] );
	$c = end( $calls );
	zt_ok( 'WhatsApp conversation starters sent', $r['ok'] && false !== strpos( $c['url'], 'PN1/conversational_automation' ) && [ 'What is the delivery charge?' ] === $c['body']['prompts'] );

	$status = CommonQuestions::sync( true );
	zt_ok( 'sync records a result per channel', isset( $status['messenger'], $status['whatsapp'] ) );

	// Buttons under a message.
	zt_fake_channel_credentials();
	$cm = zt_conversation( 'messenger', 'human', [ 'customer' => 'PS_BTN' ] );
	$id = Identity::where( 'id', $cm->identity_id )->fresh()->first();
	$mm = zt_message( $cm, 'ans', 'auto', [ 'meta' => [ 'quick_replies' => [ 'Yes, thanks', 'What is your return policy?' ] ] ] );
	Messenger::send( $cm, $id, $mm );
	$q = end( $calls )['body']['message']['quick_replies'];
	zt_ok( 'Messenger quick replies: title cut to 20, full text in the payload', 20 >= mb_strlen( $q[1]['title'] ) && 'ZAPLANE_QR:What is your return policy?' === $q[1]['payload'] );

	zt_fake_channel_credentials();
	$cw = zt_conversation( 'whatsapp', 'human', [ 'customer' => '8801700000009', 'account' => 'PN1' ] );
	$iw = Identity::where( 'id', $cw->identity_id )->fresh()->first();
	Whatsapp::send( $cw, $iw, zt_message( $cw, 'Did it help?', 'auto', [ 'meta' => [ 'quick_replies' => [ 'Yes, thanks', 'No, I need help' ] ] ] ) );
	$w = end( $calls )['body'];
	zt_ok( 'WhatsApp short labels → reply buttons', 'interactive' === $w['type'] && 'No, I need help' === $w['interactive']['action']['buttons'][1]['reply']['title'] );
	zt_fake_channel_credentials();
	Whatsapp::send( $cw, $iw, zt_message( $cw, 'Anything else?', 'auto', [ 'meta' => [ 'quick_replies' => [ 'What is your return policy?' ] ] ] ) );
	$w = end( $calls )['body'];
	zt_ok( 'WhatsApp long labels → a list menu', 'interactive' === $w['type'] && 'list' === $w['interactive']['type'] && 'What is your return policy?' === $w['interactive']['action']['sections'][0]['rows'][0]['description'] );

	// Tapping: Ice Breaker (postback) and a cut-off quick reply both arrive as the full question.
	global $wpdb;
	$wpdb->insert( \Zaplane\Models\Knowledge::getTable(), [ 'business_key' => 'zz_ch', 'title' => 'What is your return policy?', 'content' => 'Returns within 7 days.', 'source' => 'faq' ] );
	IS::save( [ 'answers' => [ 'enabled' => true, 'menu' => [] ], 'ai' => [ 'enabled' => false, 'business_key' => 'zz_ch' ] ] );
	add_filter( 'pre_http_request', fn( $pre, $a, $url ) => false !== strpos( $url, 'fields=' ) ? [ 'headers' => [], 'body' => '{}', 'response' => [ 'code' => 200 ], 'cookies' => [] ] : $pre, 5, 3 );

	$deliver = function ( array $messaging ) {
		$r = new WP_REST_Request( 'POST', '/' );
		$r->set_body( wp_json_encode( [ 'object' => 'page', 'entry' => [ [ 'id' => 'PAGE_T', 'messaging' => [ $messaging + [ 'sender' => [ 'id' => 'PS_TAP' ], 'recipient' => [ 'id' => 'PAGE_T' ], 'timestamp' => time() * 1000 ] ] ] ] ] ) );
		Messenger::ingest( $r );
	};
	$deliver( [ 'postback' => [ 'title' => 'What is your return policy?', 'payload' => 'ZAPLANE_STARTER_0', 'mid' => 'm_ice' ] ] );
	$in = Message::where( 'channel', 'messenger' )->where( 'body', 'What is your return policy?' )->orderBy( 'id', 'desc' )->fresh()->first();
	zt_ok( 'Ice Breaker tap arrives as the question', (bool) $in );
	if ( $in ) {
		KnowledgeAnswer::handle( (int) $in->conversation_id, (int) $in->id );
		$ans = Message::where( 'conversation_id', (int) $in->conversation_id )->orderBy( 'id', 'desc' )->fresh()->first();
		zt_ok( '…and gets the FAQ answer', 'auto' === $ans->sender_type && 0 === strpos( $ans->body, 'Returns within 7 days' ) );
	}
	$deliver( [ 'message' => [ 'mid' => 'm_qr', 'text' => 'What is your return…', 'quick_reply' => [ 'payload' => 'ZAPLANE_QR:What is your return policy? (again)' ] ] ] );
	zt_ok( 'cut-off quick reply arrives as its full text', (bool) Message::where( 'body', 'What is your return policy? (again)' )->fresh()->first() );
} );
