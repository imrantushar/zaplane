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
		// Its own flat menu, whatever the site has saved.
		'answers' => [ 'enabled' => true, 'strictness' => 'balanced', 'feedback' => true, 'menu' => [ 'grouped' => false, 'questions' => [ [ 'question' => 'What is your return policy?' ], [ 'question' => 'Opening hours' ], [ 'question' => 'Payment methods accepted' ] ] ] ],
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

	// Asked again in other words (not a menu tap), the same FAQ isn't repeated.
	$ask( 'whats your return policy please' );
	zt_ok( 'same FAQ not sent twice', false === strpos( (string) zt_last( $cv )->body, '30 days' ) );

	$ask( 'How long is shipping and delivery to Chattogram?' );
	$b = zt_last( $cv );
	zt_ok( 'page answer as an article card', 'article' === ( $b->attachments[0]['type'] ?? '' ) );

	$ask( 'No, I need help' );
	$menu = Presenter::message( zt_last( $cv ), true );
	zt_ok( 'no → the customer chooses: other questions or a person', 0 === strpos( $menu['body'], "Sorry that didn't help" ) && [ 'Other questions', 'Talk to a person' ] === $menu['quick_replies'] );
	zt_ok( 'no → question logged as a gap', 1 === count( KnowledgeAnswer::gaps() ) );

	$ask( 'Other questions' );
	$list = Presenter::message( zt_last( $cv ), true );
	zt_ok( '"Other questions" → the questions not asked yet, then "Talk to a person"', 'Talk to a person' === end( $list['quick_replies'] ) && in_array( 'Opening hours', $list['quick_replies'], true ) && ! in_array( 'What is your return policy?', $list['quick_replies'], true ) );

	$ask( 'Talk to a person' );
	$fresh = Conversation::where( 'id', $cv->id )->fresh()->first();
	$last2 = \Zaplane\Modules\Inbox\Models\Message::where( 'conversation_id', $cv->id )->orderBy( 'id', 'desc' )->limit( 2 )->fresh()->get()->all();
	$types = array_map( fn( $m ) => $m->sender_type, $last2 );
	zt_ok( '"Talk to a person" → the team gets a note, the customer hears back', in_array( 'system', $types, true ) && 0 === strpos( zt_last( $cv )->body, 'Sure!' ) );
	zt_ok( '…and automatic answers stay out of it', ! KnowledgeAnswer::applies( $fresh ) );

	foreach ( [ 'can I talk to a human please?', 'মানুষের সাথে কথা বলতে চাই', 'agent' ] as $typed ) {
		$ct = zt_conversation( 'web', 'human' );
		$mt = zt_message( $ct, $typed );
		KnowledgeAnswer::handle( (int) $ct->id, (int) $mt->id );
		zt_ok( "typed \"$typed\" → handed to a person", 0 === strpos( zt_last( $ct )->body, 'Sure!' ) );
	}
	zt_ok( 'a normal question is not taken as asking for a person', ! KnowledgeAnswer::wants_person( 'Do you deliver to Chattogram?' ) );

	// With the assistant on, "Try our assistant" answers the ORIGINAL question.
	$conn = \Zaplane\Models\Connection::create( [ 'user_id' => 1, 'app' => 'ai-agent', 'name' => 'ZZ AI', 'auth_type' => 'api_key', 'encrypted_credentials' => \Zaplane\Framework\Classes\Encryption::encrypt( [ 'provider' => 'anthropic', 'api_key' => 'x' ] ) ] );
	IS::save( [ 'ai' => [ 'enabled' => true, 'connection_id' => (int) $conn->id, 'business_key' => 'zz_kb' ] ] );
	$task = null;
	add_filter( 'zaplane/inbox/ai_node', function ( $node ) use ( &$task ) { $task = $node['data']['config']['task'] ?? null; $node['data']['config']['task'] = ''; return $node; } );
	$cb = zt_conversation( 'web', 'bot' );
	foreach ( [ 'what is your return policy', 'No, I need help' ] as $q ) {
		$mq = zt_message( $cb, $q );
		KnowledgeAnswer::handle( (int) $cb->id, (int) $mq->id );
	}
	zt_ok( 'assistant on → menu offers "Try our assistant"', 'Try our assistant' === ( Presenter::message( zt_last( $cb ), true )['quick_replies'][0] ?? '' ) );
	$mq = zt_message( $cb, 'Try our assistant' );
	KnowledgeAnswer::handle( (int) $cb->id, (int) $mq->id );
	zt_ok( '"Try our assistant" asks it the original question', 'what is your return policy' === rtrim( mb_strtolower( (string) $task ), '?' ) );
	IS::save( [ 'ai' => [ 'enabled' => false ] ] );

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
