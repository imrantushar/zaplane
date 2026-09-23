<?php
/**
 * The quick-answers menu: categories, questions with their own answers,
 * taps on every channel.
 */

require __DIR__ . '/_helpers.php';

use Zaplane\Modules\Inbox\Settings as IS;
use Zaplane\Modules\Inbox\Services\{AnswerMenu, KnowledgeAnswer, Presenter};
use Zaplane\Modules\Inbox\Channels\Whatsapp;
use Zaplane\Modules\Inbox\Models\{Conversation, Identity, Message};

zt_run( function () {
	global $wpdb;
	$t   = \Zaplane\Models\Knowledge::getTable();
	$ids = zt_knowledge( 'zz_menu' );
	IS::save( [ 'answers' => [ 'enabled' => true, 'feedback' => true, 'common_questions' => [ 'What is your return policy?', 'Something custom?' ] ], 'ai' => [ 'enabled' => false, 'business_key' => 'zz_menu' ] ] );
	$o = get_option( IS::OPTION ); unset( $o['answers']['menu'] ); update_option( IS::OPTION, $o, false ); // a site from before menus existed

	// Older "common questions" become a flat menu linked to their FAQs.
	$m = AnswerMenu::get();
	zt_ok( 'old common questions carried over, linked to the FAQ with the same question', 2 === count( $m['questions'] ) && $ids['refund'] === $m['questions'][0]['knowledge_id'] && 0 === $m['questions'][1]['knowledge_id'] );

	// Saving from the editor: written answers become FAQs.
	$save = function ( array $menu ) {
		$r = new WP_REST_Request( 'POST', '/zaplane/v1/inbox/settings' );
		$r->set_header( 'content-type', 'application/json' );
		$r->set_body( wp_json_encode( [ 'answers' => [ 'menu' => $menu ] ] ) );
		return rest_do_request( $r )->get_data();
	};
	$payload = $save( [
		'grouped'    => true,
		'categories' => [
			[ 'title' => '🚚 Delivery', 'questions' => [
				[ 'question' => 'Do you gift wrap orders?', 'answer' => 'Yes! Free gift wrapping on every order.' ],
				[ 'question' => 'Shipping and delivery times', 'knowledge_id' => $ids['shipping'] ],
			] ],
			[ 'title' => '💳 Payment', 'questions' => [
				[ 'question' => 'Payment methods accepted', 'knowledge_id' => $ids['pay'], 'answer' => 'bKash, Nagad, cards and cash on delivery.' ],
				[ 'question' => 'Payment methods accepted' ],
			] ],
			[ 'title' => str_repeat( 'Long category name ', 3 ), 'questions' => [] ],
			[ 'title' => '   ', 'questions' => [] ],
		],
	] );
	$menu = $payload['answers']['menu'];
	$gift = $menu['categories'][0]['questions'][0];
	zt_ok( 'written answer saved as a new FAQ and linked', $gift['knowledge_id'] > 0 && 'Yes! Free gift wrapping on every order.' === $gift['answer'] && 'faq' === $wpdb->get_var( $wpdb->prepare( 'SELECT source FROM %i WHERE id = %d', $t, $gift['knowledge_id'] ) ) );
	zt_ok( 'editing a linked FAQ answer updates that FAQ', 'bKash, Nagad, cards and cash on delivery.' === $wpdb->get_var( $wpdb->prepare( 'SELECT content FROM %i WHERE id = %d', $t, $ids['pay'] ) ) );
	zt_ok( 'duplicates dropped, blank category dropped, long title cut to 24', 1 === count( $menu['categories'][1]['questions'] ) && 3 === count( $menu['categories'] ) && 24 >= mb_strlen( $menu['categories'][2]['title'] ) );
	zt_ok( 'the old flat list is retired once a menu is saved', [] === IS::get()['answers']['common_questions'] );
	zt_ok( 'starters are the categories when grouped', [ '🚚 Delivery', '💳 Payment' ] === array_slice( AnswerMenu::starters(), 0, 2 ) );
	$w = AnswerMenu::for_widget();
	zt_ok( 'widget gets the category tree', $w['grouped'] && [ 'Do you gift wrap orders?', 'Shipping and delivery times' ] === $w['categories'][0]['questions'] );

	// Taps.
	$cv  = zt_conversation( 'web', 'human' );
	$tap = function ( string $text ) use ( $cv ) {
		$m = zt_message( $cv, $text );
		KnowledgeAnswer::handle( (int) $cv->id, (int) $m->id );
		return Presenter::message( zt_last( $cv ), true );
	};
	$r = $tap( '🚚 delivery' );
	zt_ok( 'tap a category → its questions, then All topics and Talk to a person', [ 'Do you gift wrap orders?', 'Shipping and delivery times', 'All topics', 'Talk to a person' ] === $r['quick_replies'] );
	// Use up the daily cap on automatic answers first: menu taps don't count.
	$c = Conversation::where( 'id', $cv->id )->fresh()->first();
	$c->meta = [ 'kb_log' => [ time(), time(), time() ] ];
	$c->save();
	$r = $tap( 'Do you gift wrap orders?' );
	zt_ok( 'tap a question → its own answer, even past the daily cap', 'Yes! Free gift wrapping on every order.' === $r['body'] && [ 'Yes, thanks', 'No, I need help' ] === $r['quick_replies'] );
	$r = $tap( 'Shipping and delivery times' );
	zt_ok( 'a page-backed question sends its text as the answer', 0 === strpos( $r['body'], 'We deliver across Bangladesh' ) );
	$r = $tap( 'Yes, thanks' );
	zt_ok( 'after "Yes", grouped menus offer the categories again', [ '🚚 Delivery', '💳 Payment', mb_substr( str_repeat( 'Long category name ', 3 ), 0, 24 ) ] === array_map( 'trim', $r['quick_replies'] ) || in_array( '💳 Payment', $r['quick_replies'], true ) );
	$r = $tap( 'All topics' );
	zt_ok( '"All topics" → the categories and Talk to a person', in_array( '💳 Payment', $r['quick_replies'], true ) && 'Talk to a person' === end( $r['quick_replies'] ) );

	// A question with no answer isn't a dead end: normal matching takes it.
	IS::save( [ 'answers' => [ 'menu' => [ 'grouped' => false, 'questions' => [ [ 'question' => 'What is your return policy?' ] ] ] ] ] );
	$cv2 = zt_conversation( 'web', 'human' );
	$m2  = zt_message( $cv2, 'What is your return policy?' );
	KnowledgeAnswer::handle( (int) $cv2->id, (int) $m2->id );
	zt_ok( 'unanswered menu question falls back to matching', 0 === strpos( zt_last( $cv2 )->body, 'You can return any item' ) );

	// WhatsApp: more than three (or long) options → a list menu; a tap maps back.
	$calls = [];
	zt_mock_meta( $calls );
	zt_fake_channel_credentials();
	$cw = zt_conversation( 'whatsapp', 'human', [ 'customer' => '8801700000077', 'account' => 'PN1' ] );
	$iw = Identity::where( 'id', $cw->identity_id )->fresh()->first();
	$options = [ 'Do you gift wrap orders?', 'Shipping and delivery times', 'All topics', 'Talk to a person' ];
	Whatsapp::deliver( $cw, $iw, zt_message( $cw, 'Delivery: which question?', 'auto', [ 'meta' => [ 'quick_replies' => $options ] ] ) );
	$b = end( $calls )['body'];
	zt_ok( 'WhatsApp: a list menu with one row per option', 'list' === $b['interactive']['type'] && 4 === count( $b['interactive']['action']['sections'][0]['rows'] ) && 'zqr_1' === $b['interactive']['action']['sections'][0]['rows'][1]['id'] );
	zt_ok( 'long labels are cut to 24 with the full text below', 24 >= mb_strlen( $b['interactive']['action']['sections'][0]['rows'][1]['title'] ) && 'Shipping and delivery times' === $b['interactive']['action']['sections'][0]['rows'][1]['description'] );

	$r = new WP_REST_Request( 'POST', '/' );
	$r->set_body( wp_json_encode( [ 'object' => 'whatsapp_business_account', 'entry' => [ [ 'changes' => [ [ 'field' => 'messages', 'value' => [
		'metadata' => [ 'phone_number_id' => 'PN1' ],
		'contacts' => [ [ 'wa_id' => '8801700000077', 'profile' => [ 'name' => 'ZZ' ] ] ],
		'messages' => [ [ 'id' => 'wamid.tap1', 'from' => '8801700000077', 'type' => 'interactive', 'timestamp' => (string) time(), 'interactive' => [ 'type' => 'list_reply', 'list_reply' => [ 'id' => 'zqr_1', 'title' => 'Shipping and delivery t…' ] ] ] ],
	] ] ] ] ] ] ) );
	Whatsapp::ingest( $r );
	zt_ok( 'a tapped row arrives as its full text', (bool) Message::where( 'conversation_id', $cw->id )->where( 'body', 'Shipping and delivery times' )->where( 'sender_type', 'contact' )->fresh()->first() );
} );
