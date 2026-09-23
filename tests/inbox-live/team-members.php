<?php
/**
 * Picking who answers the chat, what a picked non-manager may do, and hours
 * per person or for the whole company.
 */

require __DIR__ . '/_helpers.php';

use Zaplane\Modules\Inbox\Services\Agents;
use Zaplane\Modules\Inbox\Services\Availability;
use Zaplane\Modules\Inbox\Settings;

zt_run( function () {
	$req = function ( $method, $path, $params = [] ) {
		$r = new WP_REST_Request( $method, '/zaplane/v1/inbox/' . $path );
		// JSON, as the screen sends it (the agent endpoints read the body).
		$r->set_header( 'content-type', 'application/json' );
		$r->set_body( wp_json_encode( $params ) );
		foreach ( $params as $k => $v ) {
			$r->set_param( $k, $v );
		}
		return rest_do_request( $r );
	};
	$admin = (int) get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] )[0];
	$agent = wp_insert_user( [ 'user_login' => 'zt_agent_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'zt.agent.' . wp_rand() . '@example.com', 'role' => 'subscriber', 'display_name' => 'Zt Agent' ] );
	$other = wp_insert_user( [ 'user_login' => 'zt_other_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'zt.other.' . wp_rand() . '@example.com', 'role' => 'subscriber' ] );
	$reset = function () {
		( new ReflectionProperty( Agents::class, 'members' ) )->setValue( null, null );
		Availability::forget();
	};

	$o = get_option( Settings::OPTION, [] );
	$o['team'] = [ 'members' => [] ];
	$o['hours'] = [ 'enabled' => false ];
	update_option( Settings::OPTION, $o );
	$reset();

	zt_ok( 'nobody picked: managers answer', in_array( $admin, Agents::member_ids(), true ) && ! in_array( $agent, Agents::member_ids(), true ) );
	zt_ok( 'a subscriber may not work the inbox yet', ! user_can( $agent, Agents::CAP ) );

	wp_set_current_user( $admin );
	$d = $req( 'GET', 'agents/candidates', [ 'search' => 'zt_agent' ] )->get_data();
	zt_ok( 'search finds the user', in_array( $agent, array_column( $d['users'], 'id' ), true ) );
	$d = $req( 'POST', 'agents/add', [ 'user_id' => $agent ] )->get_data();
	$reset();
	$ids = array_column( $d['agents'], 'id' );
	zt_ok( 'first pick keeps the managers and adds the new one', in_array( $agent, $ids, true ) && in_array( $admin, $ids, true ) );
	zt_ok( 'the picked subscriber may now work the inbox', user_can( $agent, Agents::CAP ) );
	zt_ok( '…but not manage it', ! user_can( $agent, 'manage_options' ) );
	zt_ok( 'someone unpicked still may not', ! user_can( $other, Agents::CAP ) );

	wp_set_current_user( $agent );
	zt_ok( 'agent: can list conversations', 200 === $req( 'GET', 'conversations' )->get_status() );
	$d = $req( 'GET', 'settings' );
	zt_ok( 'agent: settings read is trimmed', 200 === $d->get_status() && false === $d->get_data()['can_manage'] && ! isset( $d->get_data()['ai_connections'] ) );
	zt_ok( 'agent: cannot save settings', 403 === $req( 'POST', 'settings', [ 'widget' => [ 'enabled' => false ] ] )->get_status() );
	zt_ok( 'agent: cannot add agents', 403 === $req( 'POST', 'agents/add', [ 'user_id' => $other ] )->get_status() );
	wp_set_current_user( $other );
	zt_ok( 'outsider: no conversations', in_array( $req( 'GET', 'conversations' )->get_status(), [ 401, 403 ], true ) );

	// Hours: company closed all week, agent on their own hours around now.
	wp_set_current_user( $admin );
	$closed = [];
	foreach ( Availability::DAYS as $day ) {
		$closed[ $day ] = [ 'closed' => true ];
	}
	Settings::save( [ 'hours' => [ 'enabled' => true, 'days' => $closed ] ] );
	$reset();
	zt_ok( 'company closed, everyone on company hours: chat closed', 'closed' === Availability::state()['reason'] );

	$now  = new DateTimeImmutable( 'now', wp_timezone() );
	$days = [];
	foreach ( Availability::DAYS as $day ) {
		$days[ $day ] = [ 'closed' => false, 'open' => $now->modify( '-1 hour' )->format( 'H:i' ), 'close' => $now->modify( '+1 hour' )->format( 'H:i' ) ];
	}
	$req( 'POST', 'agents/' . $agent, [ 'schedule' => [ 'mode' => 'custom', 'days' => $days ] ] );
	// Listing conversations above counted as having the inbox open.
	delete_user_meta( $agent, Agents::META_SEEN );
	$reset();
	zt_ok( 'agent on own hours: on duty', Availability::on_duty( $agent ) );
	zt_ok( 'admin on company hours: off duty', ! Availability::on_duty( $admin ) );
	zt_ok( 'chat open (someone on duty), away until they open the inbox', 'away' === Availability::state()['reason'] );
	update_user_meta( $agent, Agents::META_SEEN, time() );
	update_user_meta( $agent, Agents::META_AWAY, '0' );
	$reset();
	zt_ok( 'on duty + inbox open: online', true === Availability::state()['online'] );
	update_user_meta( $admin, Agents::META_SEEN, time() );
	$reset();
	zt_ok( 'an off-duty admin with the inbox open is not shown online', false === Agents::agent( $admin )['online'] );

	foreach ( $days as $k => $v ) {
		$days[ $k ] = [ 'closed' => true ];
	}
	$req( 'POST', 'agents/' . $agent, [ 'schedule' => [ 'mode' => 'custom', 'days' => $days ] ] );
	$reset();
	zt_ok( 'nobody on duty: closed', 'closed' === Availability::state()['reason'] );

	$req( 'DELETE', 'agents/' . $agent );
	$reset();
	zt_ok( 'removed: no longer an agent', ! in_array( $agent, Agents::member_ids(), true ) && ! user_can( $agent, Agents::CAP ) );
	$last = Agents::member_ids();
	foreach ( array_slice( $last, 1 ) as $id ) {
		$req( 'DELETE', 'agents/' . $id );
	}
	$reset();
	zt_ok( "the last agent can't be removed", 400 === $req( 'DELETE', 'agents/' . Agents::member_ids()[0] )->get_status() );
} );
