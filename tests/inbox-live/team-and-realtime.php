<?php
/**
 * Who answers and how fast the chat hears about it: agent identities, being
 * at one's desk, opening hours, the message that opens by itself, and the
 * realtime channel.
 */

require __DIR__ . '/_helpers.php';

use Zaplane\Modules\Inbox\Settings as IS;
use Zaplane\Modules\Inbox\Services\{Agents, Availability, Presenter};
use Zaplane\Socket\{Client as Socket, Frames};

zt_run( function () {
	$me = 1;

	/* ── An agent's chat identity ──────────────────────────────────────── */

	Agents::save_identity( $me, [ 'name' => 'Nila', 'title' => 'Support', 'avatar' => 'https://example.test/nila.png' ] );
	$agent = Agents::agent( $me );
	zt_ok( 'an agent can be given a chat name, role and picture', 'Nila' === $agent['name'] && 'Support' === $agent['title'] && false !== strpos( $agent['avatar'], 'nila.png' ) );
	zt_ok( '…and their account name is kept for the team to recognise them', '' !== $agent['user_name'] );

	Agents::save_identity( $me, [ 'name' => '' ] );
	zt_ok( 'clearing the chat name falls back to the account', Agents::agent( $me )['name'] === Agents::agent( $me )['user_name'] );
	Agents::save_identity( $me, [ 'name' => 'Nila' ] );

	$c       = zt_conversation( 'web', 'human' );
	$message = zt_message( $c, 'Happy to help!', 'agent', [ 'sender_id' => $me ] );
	$shown   = Presenter::message( $message, true );
	zt_ok( 'a reply is signed with that name and picture, not the account', 'Nila' === $shown['sender_name'] && false !== strpos( $shown['sender_avatar'], 'nila.png' ) && 'agent' === $shown['sender_kind'] );

	IS::save( [ 'ai' => [ 'enabled' => true, 'connection_id' => 7, 'agent_name' => 'Ava', 'avatar' => 'https://example.test/ava.png' ] ] );
	$ai = Presenter::message( zt_message( $c, 'Our returns take 14 days.', 'ai' ), true );
	zt_ok( 'an assistant reply carries its own name, picture and the AI label', false !== strpos( $ai['sender_name'], 'Ava' ) && false !== strpos( $ai['sender_name'], 'AI' ) && false !== strpos( $ai['sender_avatar'], 'ava.png' ) && 'ai' === $ai['sender_kind'] );

	/* ── At their desk ─────────────────────────────────────────────────── */

	update_user_meta( $me, Agents::META_SEEN, 0 );
	Agents::set_away( $me, false );
	update_user_meta( $me, Agents::META_SEEN, time() );
	zt_ok( 'having the inbox open makes an agent online', Agents::is_online( $me ) && Agents::anyone_online() );

	Agents::set_away( $me, true );
	zt_ok( 'stepping away takes them offline at once', ! Agents::is_online( $me ) );
	Agents::set_away( $me, false );

	update_user_meta( $me, Agents::META_SEEN, time() - ( Agents::ONLINE_WINDOW + 30 ) );
	zt_ok( 'an inbox closed a while ago no longer counts as online', ! Agents::is_online( $me ) );
	update_user_meta( $me, Agents::META_SEEN, time() );

	Agents::save_identity( $me, [ 'hidden' => true ] );
	zt_ok( 'an agent can be kept out of the chat entirely', [] === Agents::for_widget() && ! Agents::anyone_online() );
	Agents::save_identity( $me, [ 'hidden' => false ] );
	zt_ok( '…and put back', 1 === count( Agents::for_widget() ) );

	/* ── Opening hours ─────────────────────────────────────────────────── */

	$all_day = array_fill_keys( Availability::DAYS, [ 'open' => '00:00', 'close' => '23:59' ] );
	IS::save( [ 'hours' => [ 'enabled' => true, 'days' => $all_day, 'reply_time' => 'in a few minutes' ] ] );
	$state = Availability::state();
	zt_ok( 'inside the hours, with someone there, the chat is online', $state['online'] && 'agents' === $state['reason'] );

	Agents::set_away( $me, true );
	$state = Availability::state();
	zt_ok( 'inside the hours with nobody there it says so, and still takes messages', ! $state['online'] && 'away' === $state['reason'] && '' !== $state['message'] );
	Agents::set_away( $me, false );

	$today   = Availability::DAYS[ (int) ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'N' ) - 1 ];
	$shut    = $all_day;
	$shut[ $today ] = [ 'closed' => true, 'open' => '09:00', 'close' => '17:00' ];
	IS::save( [ 'hours' => [ 'enabled' => true, 'days' => $shut ] ] );
	$state = Availability::state();
	zt_ok( 'a closed day is closed however many inboxes are open', ! $state['online'] && 'closed' === $state['reason'] );
	zt_ok( '…and the chat can say when it opens again', '' !== $state['opens_at'] && false !== strtotime( $state['opens_at'] ) );

	IS::save( [ 'hours' => [ 'enabled' => false ] ] );
	zt_ok( 'with no hours kept, only the team decides', Availability::state()['online'] );

	// A night shift: open 22:00 today until 02:00 tomorrow.
	$overnight = array_fill_keys( Availability::DAYS, [ 'open' => '22:00', 'close' => '02:00' ] );
	IS::save( [ 'hours' => [ 'enabled' => true, 'days' => $overnight ] ] );
	$hour  = (int) ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'G' );
	$open  = $hour >= 22 || $hour < 2;
	zt_ok( 'hours that run past midnight are read as one shift, not none', Availability::state()['online'] === $open );
	IS::save( [ 'hours' => [ 'enabled' => false ] ] );

	/* ── What the widget is told ───────────────────────────────────────── */

	$team = Availability::team();
	zt_ok( 'the chat is given the team, with no account details in it', isset( $team['agents'][0]['name'], $team['agents'][0]['avatar'] ) && ! isset( $team['agents'][0]['id'] ) && ! isset( $team['agents'][0]['last_seen'] ) );
	zt_ok( '…and the assistant it should show', 'Ava' === ( $team['assistant']['name'] ?? '' ) && false !== strpos( (string) ( $team['assistant']['avatar'] ?? '' ), 'ava.png' ) );

	/* ── The message that opens by itself ──────────────────────────────── */

	IS::save( [ 'widget' => [ 'proactive' => [ 'enabled' => true, 'delay' => 2, 'message' => 'Need a hand?', 'repeat' => 'nonsense' ] ] ] );
	$p = IS::get()['widget']['proactive'];
	zt_ok( 'the timed greeting keeps a sane delay and a known repeat', 5 === $p['delay'] && 'session' === $p['repeat'] && 'Need a hand?' === $p['message'] );
	IS::save( [ 'widget' => [ 'proactive' => [ 'enabled' => true, 'delay' => 99999, 'repeat' => 'day' ] ] ] );
	zt_ok( '…and never a delay nobody would wait for', 600 === IS::get()['widget']['proactive']['delay'] );

	/* ── The realtime channel ──────────────────────────────────────────── */

	$roundtrip = Frames::decode( Frames::encode( str_repeat( 'zaplane ', 40 ), Frames::OP_TEXT, true ) );
	zt_ok( 'a long masked frame survives the trip', 1 === count( $roundtrip['frames'] ) && str_repeat( 'zaplane ', 40 ) === $roundtrip['frames'][0]['payload'] );
	$split = Frames::decode( substr( Frames::encode( 'half a frame' ), 0, 6 ) );
	zt_ok( 'half a frame is kept for the rest to arrive, not guessed at', [] === $split['frames'] && '' === $split['error'] );
	$huge = Frames::decode( "\x81\x7f" . pack( 'J', Frames::MAX_PAYLOAD + 1 ) );
	zt_ok( 'a frame claiming to be enormous is refused', '' !== $huge['error'] );

	zt_ok( 'an agent token proves who it is', 1 === Socket::agent_from_token( Socket::agent_token( 1 ) ) );
	zt_ok( 'a tampered agent token proves nothing', 0 === Socket::agent_from_token( 'a_99_' . ( time() + 60 ) . '.deadbeef' ) );

	IS::save( [ 'realtime' => [ 'enabled' => false, 'public_url' => 'wss://example.test/ws' ] ] );
	zt_ok( 'with realtime off the chat is told nothing about it, and polls', null === Socket::widget_config() );
	IS::save( [ 'realtime' => [ 'public_url' => 'http://example.test/ws' ] ] );
	zt_ok( 'only a ws:// or wss:// address is kept', '' === IS::get()['realtime']['public_url'] );

	// Whether a server happens to be running or not, nothing may fatal.
	IS::save( [ 'realtime' => [ 'enabled' => true, 'public_url' => 'wss://example.test/ws' ] ] );
	$pushed = Socket::push( 'v_nobody_is_listening', 'message', [ 'message_id' => 1 ] );
	zt_ok( 'a push to a server that is not there fails quietly', is_bool( $pushed ) );
	zt_ok( '…and a channel with no name is never pushed', false === Socket::push( '', 'message', [] ) );
} );
