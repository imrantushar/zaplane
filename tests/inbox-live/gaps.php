<?php
/**
 * "Questions we couldn't answer": grouping, reasons, and fixing them in place.
 */

require __DIR__ . '/_helpers.php';

use Zaplane\Modules\Inbox\Settings as IS;
use Zaplane\Modules\Inbox\Services\{KnowledgeAnswer, Presenter};

zt_run( function () {
	global $wpdb;
	$t   = \Zaplane\Models\Knowledge::getTable();
	$ids = zt_knowledge( 'zz_gap' );
	IS::save( [ 'answers' => [ 'enabled' => true, 'feedback' => true, 'menu' => [] ], 'ai' => [ 'enabled' => false, 'business_key' => 'zz_gap' ] ] );

	// Older rows (keyed by exact text, no reasons) are merged by meaning.
	update_option( KnowledgeAnswer::GAPS_OPTION, [
		md5( 'do you sell shoes?' ) => [ 'question' => 'Do you sell shoes?', 'count' => 2, 'last_at' => time() - 100, 'conversation_id' => 1 ],
		md5( 'do u sell shoes' )    => [ 'question' => 'do u sell shoes', 'count' => 1, 'last_at' => time() - 50, 'conversation_id' => 2 ],
	], false );
	KnowledgeAnswer::gap( 'Do you sell SHOES', 3 );
	$gaps  = KnowledgeAnswer::gaps();
	$shoes = $gaps[0];
	zt_ok( 'same question in other words → one row, counts added', 1 === count( $gaps ) && 4 === $shoes['count'] && 'unmatched' === $shoes['reason'] );
	zt_ok( '…keeping the wordings customers used', count( $shoes['variants'] ) >= 2 && 3 === $shoes['conversation_id'] );

	// "No, I need help" after an answer → an "unhelpful" row pointing at that answer.
	$cv  = zt_conversation( 'web', 'human' );
	$ask = function ( string $text ) use ( $cv ) {
		$m = zt_message( $cv, $text );
		KnowledgeAnswer::handle( (int) $cv->id, (int) $m->id );
	};
	$ask( 'what is your return policy' );
	$ask( 'No, I need help' );
	$refund = array_values( array_filter( KnowledgeAnswer::gaps(), fn( $g ) => 'unhelpful' === $g['reason'] ) )[0] ?? null;
	zt_ok( '"didn\'t help" row knows which answer was sent', $refund && $ids['refund'] === $refund['answer']['id'] && 'What is your return policy?' === $refund['answer']['title'] );

	$resolve = function ( string $key, array $body ) {
		$r = new WP_REST_Request( 'POST', "/zaplane/v1/inbox/knowledge/gaps/{$key}/resolve" );
		foreach ( $body as $k => $v ) {
			$r->set_param( $k, $v );
		}
		return rest_do_request( $r );
	};

	// Improve the answer that didn't help: same FAQ, new text.
	$res = $resolve( $refund['key'], [ 'question' => 'ignored', 'answer' => 'Returns within 30 days. We pick it up from your door.', 'knowledge_id' => $ids['refund'] ] );
	zt_ok( 'improve: the same FAQ gets the new answer, its question unchanged', 200 === $res->get_status() && $ids['refund'] === $res->get_data()['faq']['id'] && 'Returns within 30 days. We pick it up from your door.' === $wpdb->get_var( $wpdb->prepare( 'SELECT content FROM %i WHERE id = %d', $t, $ids['refund'] ) ) && 'What is your return policy?' === $wpdb->get_var( $wpdb->prepare( 'SELECT title FROM %i WHERE id = %d', $t, $ids['refund'] ) ) );
	zt_ok( '…and the row is gone', ! in_array( $refund['key'], array_column( $res->get_data()['gaps'], 'key' ), true ) );

	// Answer an unmatched one: a new FAQ.
	$res = $resolve( $shoes['key'], [ 'question' => 'Do you sell shoes?', 'answer' => 'Not yet! We only sell kurtas and panjabis for now.' ] );
	$faq = $res->get_data()['faq'];
	zt_ok( 'answer it: a new FAQ in the knowledge in use', $faq['id'] > 0 && 'faq' === $wpdb->get_var( $wpdb->prepare( 'SELECT source FROM %i WHERE id = %d AND business_key = %s', $t, $faq['id'], 'zz_gap' ) ) );
	zt_ok( 'the FAQ list comes back with it (for the menu editor)', in_array( 'Do you sell shoes?', array_column( $res->get_data()['faqs'], 'title' ), true ) );
	zt_ok( 'nothing left on the list', 0 === count( KnowledgeAnswer::gaps() ) );

	// The next customer gets it automatically.
	$cv2 = zt_conversation( 'web', 'human' );
	$m   = zt_message( $cv2, 'do you sell shoes' );
	KnowledgeAnswer::handle( (int) $cv2->id, (int) $m->id );
	zt_ok( 'the next customer asking gets the new answer', 0 === strpos( (string) zt_last( $cv2 )->body, 'Not yet! We only sell kurtas' ) );

	// A row with no answer on record that an FAQ matches now → review that FAQ.
	KnowledgeAnswer::gap( 'whats the return policy?', 7 );
	$now = array_values( array_filter( KnowledgeAnswer::gaps(), fn( $g ) => false !== stripos( $g['question'], 'return policy' ) ) )[0] ?? null;
	zt_ok( 'row an FAQ answers now → shows that FAQ to review', $now && $now['matched_now'] && $ids['refund'] === $now['answer']['id'] );
	KnowledgeAnswer::dismiss_gap( $now['key'] );

	// Guards.
	zt_ok( 'unknown row → 404', 404 === $resolve( str_repeat( 'a', 32 ), [ 'question' => 'x', 'answer' => 'y' ] )->get_status() );
	KnowledgeAnswer::gap( 'Do you ship abroad?', 9 );
	$key = KnowledgeAnswer::gaps()[0]['key'];
	zt_ok( 'empty answer refused', 400 === $resolve( $key, [ 'question' => 'Do you ship abroad?', 'answer' => '  ' ] )->get_status() );
} );
