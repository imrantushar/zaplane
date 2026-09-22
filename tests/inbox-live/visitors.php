<?php
/**
 * Live visitors, the team writing first, asking for (and confirming) an
 * email, and emailing replies the visitor missed.
 */

require __DIR__ . '/_helpers.php';

use Zaplane\Modules\Inbox\Settings as IS;
use Zaplane\Modules\Inbox\Services\{Visitors, VisitorContact, Conversations};
use Zaplane\Modules\Inbox\Models\{Contact, Message};

zt_run( function () {
	IS::save( [
		'widget'  => [ 'enabled' => true, 'visitors' => true, 'ask_contact' => true, 'verify_email' => false, 'notify_email' => true, 'answered_by' => 'team' ],
		'answers' => [ 'enabled' => false, 'menu' => [] ],
		'ai'      => [ 'enabled' => false ],
	] );

	// Mail is captured, never sent.
	$mails = [];
	add_filter( 'pre_wp_mail', function ( $pre, $atts ) use ( &$mails ) {
		$mails[] = $atts;
		return true;
	}, 10, 2 );

	wp_set_current_user( 0 );
	$_SERVER['REMOTE_ADDR'] = '10.7.7.' . wp_rand( 1, 250 );
	$tok  = rest_do_request( new WP_REST_Request( 'POST', '/zaplane/v1/inbox/widget/session' ) )->get_data()['token'];
	$vid  = explode( '.', $tok )[0];
	$call = function ( string $method, string $path, array $params = [] ) use ( $tok ) {
		$r = new WP_REST_Request( $method, '/zaplane/v1/inbox/widget/' . $path );
		$r->set_header( 'X-Zaplane-Visitor', $tok );
		$r->set_header( 'user_agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Mobile/15E148' );
		foreach ( $params as $k => $v ) {
			$r->set_param( $k, $v );
		}
		return rest_do_request( $r );
	};

	// Presence.
	$res = $call( 'POST', 'presence', [ 'page_url' => home_url( '/shop/' ), 'page_title' => 'Shop', 'referrer' => 'https://www.google.com/' ] );
	$call( 'POST', 'presence', [ 'page_url' => home_url( '/shop/' ), 'page_title' => 'Shop' ] );
	$call( 'POST', 'presence', [ 'page_url' => home_url( '/cart/' ), 'page_title' => 'Cart' ] );
	zt_ok( 'presence answers with the newest message id (none yet)', 200 === $res->get_status() && 0 === $res->get_data()['latest_id'] );

	wp_set_current_user( 1 );
	$list = rest_do_request( new WP_REST_Request( 'GET', '/zaplane/v1/inbox/visitors' ) )->get_data();
	$me   = array_values( array_filter( $list['visitors'], fn( $v ) => $v['visitor_id'] === $vid ) )[0] ?? null;
	zt_ok( 'visitor listed with page, device, first referrer', $me && 'Cart' === $me['page_title'] && 'mobile' === $me['device'] && 'https://www.google.com/' === $me['referrer'] );
	zt_ok( '…and 2 page views (the same page again is not a view)', $me && 2 === $me['page_views'] && $list['count'] >= 1 );

	global $wpdb;
	$wpdb->update( Visitors::table(), [ 'last_seen_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ) ], [ 'visitor_id' => $vid ] );
	zt_ok( 'gone quiet → not online', ! Visitors::is_online( $vid ) && ! in_array( $vid, array_column( Visitors::online(), 'visitor_id' ), true ) );
	$wpdb->update( Visitors::table(), [ 'last_seen_at' => gmdate( 'Y-m-d H:i:s' ) ], [ 'visitor_id' => $vid ] );

	// The team writes first.
	$r = new WP_REST_Request( 'POST', "/zaplane/v1/inbox/visitors/{$vid}/message" );
	$r->set_param( 'body', 'Hi! Need help finding a size?' );
	$res  = rest_do_request( $r );
	$conv = Conversations::find( (int) ( $res->get_data()['conversation']['id'] ?? 0 ) );
	zt_ok( 'message to a browsing visitor starts their conversation, owned by the team', 200 === $res->get_status() && $conv && 'human' === $conv->handler && 'web' === $conv->channel );

	wp_set_current_user( 0 );
	$p = $call( 'POST', 'presence', [ 'page_url' => home_url( '/cart/' ) ] )->get_data();
	zt_ok( 'their next presence report points at the new message', $p['latest_id'] === (int) $res->get_data()['message']['id'] );
	$g = $call( 'GET', 'messages' )->get_data();
	zt_ok( 'a conversation they never saw: read_upto 0, so the widget opens on it', 0 === $g['read_upto'] && 'Hi! Need help finding a size?' === $g['messages'][0]['body'] );
	zt_ok( 'a person answers and there is no email → ask for it', is_array( $g['ask_contact'] ) && '' === $g['ask_contact']['email'] && false === $g['ask_contact']['verify'] );

	// Unseen reply → emailed (once they left an email); seen reply → not.
	$call( 'POST', 'contact', [ 'name' => 'Rafi', 'email' => 'Rafi@Example.com' ] );
	$contact = Contact::where( 'id', (int) $conv->contact_id )->fresh()->first();
	zt_ok( 'no codes: the email is saved as given (lowercased), not verified', 'rafi@example.com' === $contact->email && 'Rafi' === $contact->name && ! VisitorContact::verified( $contact ) );
	zt_ok( '…and the card goes away', null === $call( 'GET', 'messages' )->get_data()['ask_contact'] );
	zt_ok( '…the team sees a note saying so', (bool) Message::where( 'conversation_id', (int) $conv->id )->where( 'is_note', 1 )->where( 'body', 'LIKE', '%rafi@example.com%' )->fresh()->first() );

	$mails = [];
	zt_ok( 'reply the visitor never saw (widget closed) → emailed', VisitorContact::notify( (int) $conv->id ) && 1 === count( $mails ) && 'rafi@example.com' === $mails[0]['to'] && false !== strpos( $mails[0]['message'], 'Hi! Need help finding a size?' ) && false !== strpos( $mails[0]['message'], '#zaplane-chat' ) );
	zt_ok( '…only once', ! VisitorContact::notify( (int) $conv->id ) );

	wp_set_current_user( 1 );
	$later = \Zaplane\Modules\Inbox\Services\Outbound::send( $conv, 'We have it in L.', [ 'sender_type' => 'agent', 'sender_id' => 1 ] );
	zt_ok( 'a team reply queues a check two minutes later', as_has_scheduled_action( VisitorContact::NOTIFY_HOOK, [ (int) $conv->id ], 'zaplane-inbox' ) );
	wp_set_current_user( 0 );
	$call( 'GET', 'messages', [ 'after_id' => $later->id - 1, 'seen' => 1 ] );
	$mails = [];
	zt_ok( 'seen in the open chat → no email', ! VisitorContact::notify( (int) $conv->id ) && 0 === count( $mails ) );

	// Codes on.
	IS::save( [ 'widget' => [ 'verify_email' => true ] ] );
	$g = $call( 'GET', 'messages' )->get_data();
	zt_ok( 'codes on: an unconfirmed email is asked about again', is_array( $g['ask_contact'] ) && true === $g['ask_contact']['verify'] && 'rafi@example.com' === $g['ask_contact']['email'] );
	wp_set_current_user( 1 );
	$later2 = \Zaplane\Modules\Inbox\Services\Outbound::send( $conv, 'Still there?', [ 'sender_type' => 'agent', 'sender_id' => 1 ] );
	$mails  = [];
	zt_ok( '…and replies are not emailed to an unconfirmed address', ! VisitorContact::notify( (int) $conv->id ) && 0 === count( $mails ) );
	wp_set_current_user( 0 );

	$mails = [];
	$res   = $call( 'POST', 'contact', [ 'email' => 'rafi@example.com' ] );
	preg_match( '/\b(\d{6})\b/', (string) ( $mails[0]['message'] ?? '' ), $m );
	zt_ok( 'a 6-digit code is emailed', 'code_sent' === ( $res->get_data()['status'] ?? '' ) && ! empty( $m[1] ) );
	zt_ok( '…the chat now shows the code step', 'rafi@example.com' === $call( 'GET', 'messages' )->get_data()['ask_contact']['pending'] );
	zt_ok( 'asking again at once is refused', 429 === $call( 'POST', 'contact', [ 'email' => 'rafi@example.com' ] )->get_status() );
	$wrong = str_pad( (string) ( ( (int) $m[1] + 1 ) % 1000000 ), 6, '0', STR_PAD_LEFT );
	zt_ok( 'a wrong code is refused', 400 === $call( 'POST', 'contact', [ 'code' => $wrong ] )->get_status() );
	$ok = $call( 'POST', 'contact', [ 'code' => substr( $m[1], 0, 3 ) . ' ' . substr( $m[1], 3 ) ] );
	$contact = Contact::where( 'id', (int) $conv->contact_id )->fresh()->first();
	zt_ok( 'the right code (spaces ignored) confirms it', 'verified' === ( $ok->get_data()['status'] ?? '' ) && VisitorContact::verified( $contact ) );
	zt_ok( '…the card is gone and the team sees Verified', null === $call( 'GET', 'messages' )->get_data()['ask_contact'] && true === \Zaplane\Modules\Inbox\Services\Presenter::contact( $contact )['email_verified'] );
	$mails = [];
	zt_ok( 'now the missed reply is emailed', VisitorContact::notify( (int) $conv->id ) && 1 === count( $mails ) && false !== strpos( $mails[0]['message'], 'Still there?' ) );

	// Five wrong guesses lock the code.
	$c2 = zt_conversation( 'web', 'human' );
	Conversations::set_meta( $c2, [ 'contact_pending' => [ 'email' => 'x@example.com', 'hash' => 'nope', 'expires' => time() + 600, 'tries' => 5 ] ] );
	zt_ok( 'after 5 wrong codes, even a guess is refused (429)', 429 === ( VisitorContact::verify( $c2, '123456' )->get_error_data()['status'] ?? 0 ) );

	// "Not now".
	$c3 = zt_conversation( 'web', 'human' );
	zt_ok( 'no email yet → asked', null !== VisitorContact::ask( $c3 ) );
	VisitorContact::skip( $c3 );
	zt_ok( '"Not now" → not asked again in this conversation', null === VisitorContact::ask( $c3 ) );
	// Automatic answers on: only once they ask for a person.
	IS::save( [ 'answers' => [ 'enabled' => true ] ] );
	$c5 = zt_conversation( 'web', 'human' );
	zt_message( $c5, 'what is your return policy' );
	zt_ok( 'automatic answers still helping → not asked yet', null === VisitorContact::ask( $c5 ) );
	$m5 = zt_message( $c5, 'Talk to a person' );
	\Zaplane\Modules\Inbox\Services\KnowledgeAnswer::handle( (int) $c5->id, (int) $m5->id );
	zt_ok( '"Talk to a person" (after a quick question) → name + email asked', is_array( VisitorContact::ask( Conversations::find( (int) $c5->id ) ) ) && VisitorContact::ask( Conversations::find( (int) $c5->id ) )['name'] === false );
	$c4 = zt_conversation( 'web', 'bot' );
	zt_ok( 'the assistant answering → not asked yet', null === VisitorContact::ask( $c4 ) );
	zt_ok( 'a bad address is refused', 400 === ( VisitorContact::submit( $c3, '', 'not-an-email' )->get_error_data()['status'] ?? 0 ) );
} );
