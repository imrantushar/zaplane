<?php
/**
 * Automatic answers from Business Knowledge: matching, replies, feedback,
 * the customer-facing acknowledgements, and the widget's "typing" signal.
 */

require __DIR__ . '/_helpers.php';

use Zaplane\Modules\Inbox\Settings as IS;
use Zaplane\Modules\Inbox\Services\{KnowledgeMatch, KnowledgeAnswer, Router, Presenter};
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Services\KnowledgeEmbeddings as KE;

zt_run( function () {
	delete_option( KnowledgeAnswer::GAPS_OPTION );
	delete_option( KnowledgeAnswer::STATS_OPTION );
	update_option( KE::OPTION, [ 'enabled' => false ], false );
	$ids = zt_knowledge( 'zz_kb' );

	// Matching (words only).
	$m = KnowledgeMatch::find( 'zz_kb', "What's your return policy?" );
	zt_ok( 'FAQ question → strong', 'strong' === $m['tier'] && $ids['refund'] === $m['entries'][0]['id'] );
	zt_ok( 'chat spelling "whats" still matches', 'strong' === KnowledgeMatch::find( 'zz_kb', 'whats the return policy' )['tier'] );
	zt_ok( 'Bangla question → strong', 'strong' === KnowledgeMatch::find( 'zz_kb', 'ফেরত নীতি কী?' )['tier'] );
	$m = KnowledgeMatch::find( 'zz_kb', 'How long is shipping and delivery to Chattogram?' );
	zt_ok( 'page → medium with link', 'medium' === $m['tier'] && get_permalink( $ids['page'] ) === $m['entries'][0]['url'] );
	zt_ok( 'greeting ignored', 'small_talk' === KnowledgeMatch::find( 'zz_kb', 'Hi!' )['reason'] );
	zt_ok( 'one word ignored', 'too_short' === KnowledgeMatch::find( 'zz_kb', 'refund' )['reason'] );
	zt_ok( 'unrelated → none', 'none' === KnowledgeMatch::find( 'zz_kb', 'Do you sell laptops or phones?' )['tier'] );

	// Replies in a team conversation (assistant off).
	IS::save( [
		'answers' => [ 'enabled' => true, 'strictness' => 'balanced', 'feedback' => true, 'common_questions' => [ 'What is your return policy?', 'Opening hours', 'Payment methods accepted' ] ],
		'ai'      => [ 'enabled' => false, 'business_key' => 'zz_kb' ],
	] );
	$cv  = zt_conversation( 'web', 'human' );
	$ask = function ( string $text ) use ( $cv ) {
		$m = zt_message( $cv, $text );
		KnowledgeAnswer::handle( (int) $cv->id, (int) $m->id );
		return $m;
	};

	$ask( 'what is your return policy' );
	$a = zt_last( $cv );
	$p = Presenter::message( $a, true );
	zt_ok( 'FAQ answer sent as "Automatic answer"', 'auto' === $a->sender_type && 0 === strpos( $a->body, 'You can return any item' ) && 'Automatic answer' === $p['sender_name'] );
	zt_ok( 'feedback buttons + prompt in widget payload', [ 'Yes, thanks', 'No, I need help' ] === $p['quick_replies'] && 'Did this answer your question?' === $p['quick_prompt'] );

	$ask( 'Yes, thanks' );
	$y = Presenter::message( zt_last( $cv ), true );
	zt_ok( 'yes → "Anything else?" with the questions not asked yet', 0 === strpos( $y['body'], 'Glad that helped!' ) && [ 'Opening hours', 'Payment methods accepted' ] === $y['quick_replies'] && '' !== $y['quick_prompt'] );

	$ask( 'what is your return policy?' );
	zt_ok( 'same FAQ not sent twice', 'contact' === zt_last( $cv )->sender_type || 'auto' !== zt_last( $cv )->sender_type || false === strpos( zt_last( $cv )->body, '30 days' ) );

	$ask( 'How long is shipping and delivery to Chattogram?' );
	$b = zt_last( $cv );
	zt_ok( 'page answer as an article card', 'article' === ( $b->attachments[0]['type'] ?? '' ) );

	$ask( 'No, I need help' );
	$fresh = Conversation::where( 'id', $cv->id )->fresh()->first();
	$msgs  = array_map( fn( $m ) => $m->sender_type . ':' . mb_substr( $m->body, 0, 20 ), array_slice( \Zaplane\Modules\Inbox\Models\Message::where( 'conversation_id', $cv->id )->orderBy( 'id', 'desc' )->limit( 2 )->fresh()->get()->all(), 0, 2 ) );
	zt_ok( 'no → the customer is told the team will reply, the team gets a note', false !== strpos( implode( '|', $msgs ), "auto:No problem. I've pa" ) && false !== strpos( implode( '|', $msgs ), 'system:' ) );
	zt_ok( 'no → question logged as a gap', 1 === count( KnowledgeAnswer::gaps() ) );

	// A question nothing answers, in a fresh team conversation: told once.
	$cv2  = zt_conversation( 'web', 'human' );
	$m1   = zt_message( $cv2, 'Do you sell laptops or phones?' );
	KnowledgeAnswer::handle( (int) $cv2->id, (int) $m1->id );
	zt_ok( 'unanswered → "Thanks! Our team will reply here soon."', 0 === strpos( zt_last( $cv2 )->body, 'Thanks! Our team will reply' ) );
	$m2 = zt_message( $cv2, 'And tablets?' );
	KnowledgeAnswer::handle( (int) $cv2->id, (int) $m2->id );
	zt_ok( '…but not again for every message', 'contact' === zt_last( $cv2 )->sender_type );

	// Daily cap and team takeover.
	$cv3 = zt_conversation( 'web', 'human' );
	foreach ( [ 'what is your return policy', 'what are your opening hours', 'which payment methods accepted' ] as $q ) {
		$m = zt_message( $cv3, $q );
		KnowledgeAnswer::handle( (int) $cv3->id, (int) $m->id );
		$c = Conversation::where( 'id', $cv3->id )->fresh()->first();
		$meta = $c->meta; unset( $meta['kb_pending'] ); $c->meta = $meta; $c->save();
	}
	$m = zt_message( $cv3, 'how long is shipping and delivery to Chattogram' );
	KnowledgeAnswer::handle( (int) $cv3->id, (int) $m->id );
	zt_ok( 'fourth automatic answer in a day is blocked', false === strpos( (string) zt_last( $cv3 )->body, "Here's what we have" ) );
	zt_message( $cv3, 'Hi, Tushar here', 'agent', [ 'sender_id' => 1 ] );
	zt_ok( 'after a teammate replies, automatic answers stop', ! KnowledgeAnswer::applies( Conversation::where( 'id', $cv3->id )->fresh()->first() ) );

	// A job that already ran (or a reply that got there first) doesn't reply twice.
	$cv4 = zt_conversation( 'web', 'human' );
	$m   = zt_message( $cv4, 'what is your return policy' );
	KnowledgeAnswer::handle( (int) $cv4->id, (int) $m->id );
	KnowledgeAnswer::handle( (int) $cv4->id, (int) $m->id );
	zt_ok( 'running the same job twice answers once', 1 === \Zaplane\Modules\Inbox\Models\Message::where( 'conversation_id', $cv4->id )->where( 'sender_type', 'auto' )->fresh()->count() );

	// Router queues the job, and the widget shows "typing" while it waits.
	$cv5 = zt_conversation( 'web', 'human' );
	$m5  = zt_message( $cv5, 'what is your return policy' );
	Router::route( $cv5, $m5 );
	zt_ok( 'router queued the knowledge job', as_has_scheduled_action( KnowledgeAnswer::HOOK, [ (int) $cv5->id, (int) $m5->id ], Router::AS_GROUP ) );
	$typing = new ReflectionMethod( \Zaplane\Modules\Inbox\Api\WidgetController::class, 'assistant_is_typing' );
	$typing->setAccessible( true );
	zt_ok( 'widget shows typing while the answer is queued', true === $typing->invoke( new \Zaplane\Modules\Inbox\Api\WidgetController(), $cv5 ) );
	KnowledgeAnswer::handle( (int) $cv5->id, (int) $m5->id );
	zt_ok( '…and stops once it is answered', false === $typing->invoke( new \Zaplane\Modules\Inbox\Api\WidgetController(), $cv5 ) );

	// Preview for the settings "Test a question" box.
	$r = KnowledgeAnswer::preview( 'What is the return policy' );
	zt_ok( 'test box shows what would be sent', 'strong' === $r['tier'] && false !== strpos( $r['reply']['body'], '30 days' ) );

	// Matching by meaning, with injected vectors (no API needed).
	$vec = function ( $t ) {
		$t = mb_strtolower( $t );
		return [ preg_match( '/refund|return|money back/', $t ) ? 1.0 : 0.05, preg_match( '/ship|deliver|arrive/', $t ) ? 1.0 : 0.05, preg_match( '/pay|bkash|cash/', $t ) ? 1.0 : 0.05 ];
	};
	add_filter( 'zaplane_embeddings_vector', fn( $v, $text ) => $vec( $text ), 10, 2 );
	update_option( KE::OPTION, [ 'enabled' => true, 'connection_id' => 0, 'model' => '' ], false );
	global $wpdb;
	$t = \Zaplane\Models\Knowledge::getTable();
	$wpdb->query( $wpdb->prepare( 'UPDATE %i SET embedding = %s WHERE id = %d', $t, KE::encode( $vec( 'refund' ), KE::tag() ), $ids['refund'] ) );
	$wpdb->query( $wpdb->prepare( 'UPDATE %i SET embedding = %s WHERE id = %d', $t, KE::encode( [ 0.8, 0.6, 0.05 ], KE::tag() ), $ids['shipping'] ) );
	$m = KnowledgeMatch::find( 'zz_kb', 'Can I get my money back?' );
	zt_ok( 'meaning match without shared words → strong', 'semantic' === $m['method'] && 'strong' === $m['tier'] && $ids['refund'] === $m['entries'][0]['id'] );
	zt_ok( 'partial meaning → medium page', 'medium' === KnowledgeMatch::find( 'zz_kb', 'When will my parcel arrive?' )['tier'] );
} );
